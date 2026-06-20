/**
 * quiz_engine.js
 * Mobile UI/UX Interactive Quiz Player.
 * Renders all 5 question types dynamically, supports touch drag-and-drop,
 * matching lines connections, audio synthesis, and secure REST scoring submissions.
 */

class QuizEngine {
    constructor(config) {
        this.quizId = config.quizId;
        this.lessonId = config.lessonId;
        this.totalMarks = config.totalMarks;
        this.questions = config.questions; // nested array with options
        
        this.currentIdx = 0;
        this.score = 0;
        this.correctCount = 0;
        this.userAnswers = {}; // Mapped responses for validation
        
        // Element references
        this.optionsContainer = document.getElementById('quiz_options_container');
        this.feedbackBanner = document.getElementById('quiz_feedback_banner');
        this.submitBtn = document.getElementById('quiz_submit_btn');
        this.progressText = document.getElementById('quiz_progress_text');
        this.progressBarFill = document.getElementById('quiz_progress_bar_fill');
        this.questionText = document.getElementById('quiz_question_text');
        
        // Match system state
        this.selectedLeft = null;
        this.selectedLeftVal = null;
        this.matches = []; // array of {left, right}
        
        // Drag assignments state
        this.dragAssignments = {}; // {itemText: categoryBucket}
    }

    /**
     * Start the quiz loop.
     */
    start() {
        this.currentIdx = 0;
        this.score = 0;
        this.correctCount = 0;
        this.showQuestion();
    }

    /**
     * Render the active question according to its type.
     */
    showQuestion() {
        if (this.currentIdx >= this.questions.length) {
            this.completeQuiz();
            return;
        }

        const q = this.questions[this.currentIdx];
        
        // Reset state parameters
        this.selectedLeft = null;
        this.selectedLeftVal = null;
        this.matches = [];
        this.dragAssignments = {};
        this.userAnswers = {};

        // Update progress visual headers
        this.progressText.innerText = `Question ${this.currentIdx + 1} of ${this.questions.length}`;
        const progressPercent = ((this.currentIdx) / this.questions.length) * 100;
        this.progressBarFill.style.width = `${progressPercent}%`;
        this.questionText.innerText = q.question_text;

        // Reset Feedback Banner and Buttons
        this.feedbackBanner.innerText = "";
        this.feedbackBanner.className = "quiz-feedback-banner";
        this.optionsContainer.innerHTML = "";
        this.submitBtn.style.display = "none";

        // Render input interfaces
        switch (q.question_type) {
            case 'single_choice':
                this.renderSingleChoice(q.options);
                break;
            case 'multiple_choice':
                this.renderMultipleChoice(q.options);
                break;
            case 'fill_in_the_blank':
                this.renderFillInBlank();
                break;
            case 'drag_and_put':
                this.renderDragAndPut(q.options);
                break;
            case 'connecting_the_link':
                this.renderConnectingLink(q.options);
                break;
        }
    }

    /**
     * Single Choice - big friendly radio options.
     */
    renderSingleChoice(options) {
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'quiz-option-card';
            btn.innerText = opt.option_text;
            btn.type = 'button';
            btn.addEventListener('click', () => {
                // Clear selection on other cards
                document.querySelectorAll('.quiz-option-card').forEach(c => c.classList.remove('selected'));
                btn.classList.add('selected');
                
                // Save selection
                this.userAnswers.single = opt.option_text;
                
                // Show validation triggers
                this.submitBtn.style.display = "block";
                this.submitBtn.innerText = "Check Answer! 🌟";
            });
            this.optionsContainer.appendChild(btn);
        });
    }

    /**
     * Multiple Choice - toggleable selections.
     */
    renderMultipleChoice(options) {
        this.userAnswers.multi = [];
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'quiz-option-card';
            btn.innerText = opt.option_text;
            btn.type = 'button';
            btn.addEventListener('click', () => {
                btn.classList.toggle('selected');
                
                // Update selection array
                const idx = this.userAnswers.multi.indexOf(opt.option_text);
                if (idx > -1) {
                    this.userAnswers.multi.splice(idx, 1);
                } else {
                    this.userAnswers.multi.push(opt.option_text);
                }
                
                // Toggle action buttons
                if (this.userAnswers.multi.length > 0) {
                    this.submitBtn.style.display = "block";
                    this.submitBtn.innerText = "Check Answers! 🌟";
                } else {
                    this.submitBtn.style.display = "none";
                }
            });
            this.optionsContainer.appendChild(btn);
        });
    }

    /**
     * Fill in the Blank - clean inline text inputs.
     */
    renderFillInBlank() {
        const wrapper = document.createElement('div');
        wrapper.className = 'fill-blank-wrapper';
        wrapper.innerHTML = `
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:8px;">Type your response inside the box below:</p>
            <input type="text" id="blank_input" class="form-control" style="font-size:18px; padding:15px; text-align:center; border-radius:16px; border:3px solid #e2ebf5;" placeholder="Type here..." autocomplete="off">
        `;
        this.optionsContainer.appendChild(wrapper);

        const input = document.getElementById('blank_input');
        input.addEventListener('input', () => {
            if (input.value.trim() !== "") {
                this.submitBtn.style.display = "block";
                this.submitBtn.innerText = "Submit Response! 🌟";
                this.userAnswers.blank = input.value.trim();
            } else {
                this.submitBtn.style.display = "none";
            }
        });
    }

    /**
     * Drag & Put - touch drag-and-drop system.
     */
    renderDragAndPut(options) {
        // Collect items and categories
        const itemsPool = options.map(o => o.option_text);
        
        // Extract unique categories (ignoring null/empty)
        const categories = [...new Set(options.map(o => o.category).filter(c => c && c.trim() !== ""))];

        const wrapper = document.createElement('div');
        wrapper.className = 'drag-drop-layout';
        wrapper.innerHTML = `
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:12px;">Drag items into their matching category bubbles below:</p>
            <div class="drag-items-pool" id="drag_pool"></div>
            <div class="buckets-container" id="buckets_container"></div>
        `;
        this.optionsContainer.appendChild(wrapper);

        const pool = document.getElementById('drag_pool');
        const buckets = document.getElementById('buckets_container');

        // Create draggable elements
        itemsPool.forEach(itemText => {
            const el = document.createElement('div');
            el.className = 'drag-item';
            el.innerText = itemText;
            el.dataset.itemText = itemText;
            
            // Enable unified pointer drag handler (mouse + touch)
            this.bindPointerDragEvents(el);
            pool.appendChild(el);
        });

        // Create landing category buckets
        categories.forEach(catName => {
            const b = document.createElement('div');
            b.className = 'category-bucket';
            b.dataset.categoryName = catName;
            b.innerHTML = `
                <div class="bucket-title">${catName}</div>
                <div class="bucket-items-list"></div>
            `;
            buckets.appendChild(b);
        });
        
        // Always display submit option for Drag & Put
        this.submitBtn.style.display = "block";
        this.submitBtn.innerText = "Check Categories! 🌟";
    }

    /**
     * Unified pointer events binder to allow smooth mouse and touch drag operations.
     */
    bindPointerDragEvents(item) {
        const self = this;
        
        item.addEventListener('pointerdown', (e) => {
            // Only drag on left-click for mouse
            if (e.button !== 0 && e.pointerType === 'mouse') return;
            
            // Capture pointer moves and releases even outside the boundary
            item.setPointerCapture(e.pointerId);
            
            const rect = item.getBoundingClientRect();
            
            // Calculate cursor/finger relative offset inside the element
            item.dataset.offsetX = e.clientX - rect.left;
            item.dataset.offsetY = e.clientY - rect.top;
            
            // Set styles for dragging
            item.style.position = 'fixed';
            item.style.width = rect.width + 'px';
            item.style.height = rect.height + 'px';
            item.style.zIndex = '9999';
            item.style.pointerEvents = 'none'; // CRITICAL: Allows elementFromPoint to see through the item!
            
            moveAt(e.clientX, e.clientY);
            
            function onPointerMove(ev) {
                moveAt(ev.clientX, ev.clientY);
            }
            
            function onPointerUp(ev) {
                item.releasePointerCapture(ev.pointerId);
                
                item.removeEventListener('pointermove', onPointerMove);
                item.removeEventListener('pointerup', onPointerUp);
                
                // Find target underneath the release coordinates while pointer-events is still 'none'
                const releasedOn = document.elementFromPoint(ev.clientX, ev.clientY);
                
                // Restore pointer-events so it is interactive again
                item.style.pointerEvents = 'auto';
                
                // Reset styles
                item.style.position = '';
                item.style.zIndex = '';
                item.style.width = '';
                item.style.height = '';
                item.style.left = '';
                item.style.top = '';
                
                const targetBucket = releasedOn ? releasedOn.closest('.category-bucket') : null;
                if (targetBucket) {
                    targetBucket.querySelector('.bucket-items-list').appendChild(item);
                    self.dragAssignments[item.dataset.itemText] = targetBucket.dataset.categoryName;
                } else {
                    document.getElementById('drag_pool').appendChild(item);
                    delete self.dragAssignments[item.dataset.itemText];
                }
            }
            
            item.addEventListener('pointermove', onPointerMove);
            item.addEventListener('pointerup', onPointerUp);
        });

        function moveAt(clientX, clientY) {
            const ox = parseFloat(item.dataset.offsetX) || 0;
            const oy = parseFloat(item.dataset.offsetY) || 0;
            item.style.left = (clientX - ox) + 'px';
            item.style.top = (clientY - oy) + 'px';
        }
    }

    /**
     * Connecting the Link - Two-Tap Pairing layout.
     */
    renderConnectingLink(options) {
        // Collect left side (item) and right side (matching_pair) values
        const leftItems = options.map(o => o.option_text);
        
        // Shuffle right side to make it interactive and challenging
        const rightItems = options.map(o => o.matching_pair).sort(() => Math.random() - 0.5);

        const wrapper = document.createElement('div');
        wrapper.className = 'matching-game-layout';
        wrapper.innerHTML = `
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:12px;">Tap one card on the left, then match it with a card on the right!</p>
            <div class="matching-cols-container">
                <div class="match-column" id="left_match_col"></div>
                <div class="match-column" id="right_match_col"></div>
            </div>
            <div class="matched-pairs-label" style="font-size:0.9rem; font-weight:800; color:var(--color-purple); display:none;" id="pairs_label">Matched Connections:</div>
            <div class="matched-pairs-list" id="matched_pairs_list"></div>
        `;
        this.optionsContainer.appendChild(wrapper);

        const leftCol = document.getElementById('left_match_col');
        const rightCol = document.getElementById('right_match_col');

        // Draw Left column elements
        leftItems.forEach(leftText => {
            const card = document.createElement('div');
            card.className = 'match-item';
            card.innerText = leftText;
            card.dataset.side = 'left';
            card.dataset.val = leftText;
            card.addEventListener('click', () => this.handleMatchSelection(card));
            leftCol.appendChild(card);
        });

        // Draw Right column elements
        rightItems.forEach(rightText => {
            const card = document.createElement('div');
            card.className = 'match-item';
            card.innerText = rightText;
            card.dataset.side = 'right';
            card.dataset.val = rightText;
            card.addEventListener('click', () => this.handleMatchSelection(card));
            rightCol.appendChild(card);
        });

        this.submitBtn.style.display = "block";
        this.submitBtn.innerText = "Check Connections! 🌟";
    }

    /**
     * Handles clicks on Two-Tap matching cards.
     */
    handleMatchSelection(card) {
        const side = card.dataset.side;
        const val = card.dataset.val;

        if (card.classList.contains('matched')) return;

        if (side === 'left') {
            // Remove highlight on old left selection
            if (this.selectedLeft) {
                this.selectedLeft.classList.remove('selected');
            }
            this.selectedLeft = card;
            this.selectedLeftVal = val;
            card.classList.add('selected');
        } else {
            // Check if we have an active left choice ready to pair
            if (this.selectedLeftVal) {
                const rightVal = val;
                
                // Save connection
                this.matches.push({ left: this.selectedLeftVal, right: rightVal });

                // Dim matched components so they can't be selected again
                this.selectedLeft.classList.remove('selected');
                this.selectedLeft.classList.add('matched');
                card.classList.add('matched');

                // Render match bubble indicator at bottom
                document.getElementById('pairs_label').style.display = 'block';
                const container = document.getElementById('matched_pairs_list');
                
                const bubble = document.createElement('div');
                bubble.className = 'pair-bubble';
                bubble.innerHTML = `
                    <span>${this.selectedLeftVal} 🔗 ${rightVal}</span>
                    <button type="button" class="btn-remove-pair" style="background:none; border:none; margin-left:8px; cursor:pointer;">❌</button>
                `;
                
                const leftSaved = this.selectedLeft;
                const leftValSaved = this.selectedLeftVal;
                
                bubble.querySelector('.btn-remove-pair').addEventListener('click', () => {
                    // Remove match pair configuration on click
                    this.matches = this.matches.filter(m => !(m.left === leftValSaved && m.right === rightVal));
                    leftSaved.classList.remove('matched');
                    card.classList.remove('matched');
                    bubble.remove();
                    
                    if (this.matches.length === 0) {
                        document.getElementById('pairs_label').style.display = 'none';
                    }
                });

                container.appendChild(bubble);

                // Reset variables
                this.selectedLeft = null;
                this.selectedLeftVal = null;
            } else {
                // If they tapped right first, gently prompt them to select left
                alert("Choose a card on the left first! 🐵");
            }
        }
    }

    /**
     * Triggered when checking response answers.
     */
    checkAnswer() {
        // Disable submission during validation step
        this.submitBtn.disabled = true;
        
        const q = this.questions[this.currentIdx];
        let isCorrect = false;

        if (q.question_type === 'single_choice') {
            const correctOpt = q.options.find(o => o.is_correct == 1);
            if (this.userAnswers.single === correctOpt.option_text) {
                isCorrect = true;
            }
        } else if (q.question_type === 'multiple_choice') {
            const correctTexts = q.options.filter(o => o.is_correct == 1).map(o => o.option_text);
            const userTexts = this.userAnswers.multi || [];
            
            // Check if correct arrays size match and all user choices are inside correct choices
            if (correctTexts.length === userTexts.length && userTexts.every(val => correctTexts.includes(val))) {
                isCorrect = true;
            }
        } else if (q.question_type === 'fill_in_the_blank') {
            const correctText = q.options[0].option_text.trim().toLowerCase();
            const userText = (this.userAnswers.blank || "").trim().toLowerCase();
            if (correctText === userText) {
                isCorrect = true;
            }
        } else if (q.question_type === 'drag_and_put') {
            // Drag and Drop validation: check all items match their respective categories
            isCorrect = true;
            q.options.forEach(opt => {
                const assigned = this.dragAssignments[opt.option_text];
                if (assigned !== opt.category) {
                    isCorrect = false;
                }
            });
        } else if (q.question_type === 'connecting_the_link') {
            // Two-Tap matching validation: check all left-right matches are in sync
            isCorrect = true;
            q.options.forEach(opt => {
                const matchFound = this.matches.find(m => m.left === opt.option_text && m.right === opt.matching_pair);
                if (!matchFound) {
                    isCorrect = false;
                }
            });
        }

        // Play Synthesizer sfx and update visual notifications
        if (isCorrect) {
            this.correctCount++;
            this.feedbackBanner.innerText = "🌟 AWESOME! That's correct! 🌟";
            this.feedbackBanner.className = "quiz-feedback-banner feedback-correct";
            if (window.sfx) sfx.playSuccess();
        } else {
            this.feedbackBanner.innerText = "❌ Oops! Let's try again next time! ❌";
            this.feedbackBanner.className = "quiz-feedback-banner feedback-wrong";
            if (window.sfx) sfx.playError();
            
            // If Single or Multiple Choice, show correct answers visually
            if (q.question_type === 'single_choice' || q.question_type === 'multiple_choice') {
                const correctTexts = q.options.filter(o => o.is_correct == 1).map(o => o.option_text);
                document.querySelectorAll('.quiz-option-card').forEach(btn => {
                    if (correctTexts.includes(btn.innerText)) {
                        btn.classList.add('correct-outline');
                    }
                });
            }
        }

        // Show Next Question button after a short reading delay
        setTimeout(() => {
            this.submitBtn.disabled = false;
            this.currentIdx++;
            this.showQuestion();
        }, 2200);
    }

    /**
     * Submit final scores to back-end endpoints.
     */
    completeQuiz() {
        // Calculate points based on proportion of correct questions
        const quizScoreVal = Math.round((this.correctCount / this.questions.length) * this.totalMarks);
        
        // Render loading state
        this.optionsContainer.innerHTML = `
            <div style="text-align:center; padding: 30px 0;">
                <p style="font-size:1.5rem;">🎉 Uploading stars...</p>
                <div class="trophy-loading-spinner" style="font-size: 3.5rem; animation: gentle-bounce 1s infinite alternate;">🏆</div>
            </div>
        `;
        
        fetch('save_score.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lesson_id=${this.lessonId}&score=${quizScoreVal}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert("Score submission error: " + data.error);
                return;
            }
            
            // Redirect or show celebration modal popup details
            this.renderCelebration(quizScoreVal, data.xp_earned, data.new_streak);
        })
        .catch(err => {
            console.error("Connection error saving scores:", err);
            // Fallback render
            this.renderCelebration(quizScoreVal, quizScoreVal * 10, 1);
        });
    }

    /**
     * Draw final celebration dashboard summary.
     */
    renderCelebration(scoreVal, xpEarned, newStreak) {
        this.questionText.innerText = "Congratulations! 🎉";
        this.feedbackBanner.style.display = "none";
        this.submitBtn.style.display = "none";
        this.progressText.innerText = "Quiz Completed!";
        this.progressBarFill.style.width = "100%";

        this.optionsContainer.innerHTML = `
            <div class="celebration-container" style="text-align:center; padding: 20px 0; animation: gentle-bounce 1.5s ease infinite alternate;">
                <div style="font-size: 5.5rem; margin-bottom:15px; animation: trophy-bounce 0.8s ease infinite alternate;">🏆</div>
                <h2 style="color:var(--color-purple); font-size:2rem; margin-bottom:10px;">You are a Star!</h2>
                <p style="font-size:1.15rem; font-weight:800; color:var(--text-color); margin-bottom:8px;">Marks Earned: ${this.correctCount} / ${this.questions.length} Correct!</p>
                <p style="font-size:1.25rem; font-weight:800; color:var(--color-secondary); margin-bottom:15px;">⭐ +${xpEarned} Gold Stars added! ⭐</p>
                ${newStreak ? `<p style="font-size:1.1rem; font-weight:800; color:var(--color-primary);">🔥 Keep it up! Streak: ${newStreak} Days!</p>` : ''}
                <div style="margin-top:25px;">
                    <a href="course.php" class="btn btn-success" style="display:inline-block; text-decoration:none; padding:15px 30px; font-size:1.1rem; border-radius:18px;">
                        Back to Map 🗺️
                    </a>
                </div>
            </div>
        `;
    }
}
