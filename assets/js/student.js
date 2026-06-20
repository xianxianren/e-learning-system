/**
 * student.js
 * Handles student interactions including PIN entry, custom video playback controls,
 * and the interactive quiz game with real-time audio and progress persistence.
 */

document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------------------
    // 1. PIN Login Screen Logic
    // -------------------------------------------------------------------------
    const avatarCards = document.querySelectorAll('.avatar-card');
    const studentIdInput = document.getElementById('selected_student_id');
    const pinDots = document.querySelectorAll('.pin-dot');
    const loginForm = document.getElementById('student_login_form');
    let enteredPin = "";

    // Avatar Selection
    avatarCards.forEach(card => {
        card.addEventListener('click', () => {
            avatarCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            // Set hidden field to user_id
            if (studentIdInput) {
                studentIdInput.value = card.dataset.userId;
            }

            // Reset PIN input on avatar switch
            enteredPin = "";
            updatePinDots();
        });
    });

    // Custom Keypad Button Taps
    const keypadButtons = document.querySelectorAll('.keypad-btn');
    keypadButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.dataset.val;

            if (val === 'back') {
                if (enteredPin.length > 0) {
                    enteredPin = enteredPin.slice(0, -1);
                }
            } else if (val === 'clear') {
                enteredPin = "";
            } else {
                // Only allow up to 4 digits
                if (enteredPin.length < 4) {
                    enteredPin += val;
                }
            }

            updatePinDots();

            // Submit if 4 digits are completed and avatar is selected
            if (enteredPin.length === 4) {
                if (studentIdInput && studentIdInput.value === "") {
                    alert("Tap your avatar first! 🐵🐰🐼");
                    enteredPin = "";
                    updatePinDots();
                } else {
                    submitPinLogin();
                }
            }
        });
    });

    function updatePinDots() {
        pinDots.forEach((dot, idx) => {
            if (idx < enteredPin.length) {
                dot.classList.add('filled');
            } else {
                dot.classList.remove('filled');
            }
        });
    }

    function submitPinLogin() {
        if (!loginForm) return;

        // Append a hidden input containing the completed pin
        let pinInput = document.getElementById('completed_pin');
        if (!pinInput) {
            pinInput = document.createElement('input');
            pinInput.type = 'hidden';
            pinInput.id = 'completed_pin';
            pinInput.name = 'pin';
            loginForm.appendChild(pinInput);
        }
        pinInput.value = enteredPin;

        // Submit standard form
        loginForm.submit();
    }

    // -------------------------------------------------------------------------
    // 2. Custom Video Player Controls
    // -------------------------------------------------------------------------
    const lessonVideo = document.getElementById('lesson_video');
    const playPauseBtn = document.getElementById('play_pause_btn');
    const rewindBtn = document.getElementById('rewind_btn');
    const takeQuizBtn = document.getElementById('take_quiz_btn');

    if (lessonVideo) {
        // Toggle play/pause
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', () => {
                if (lessonVideo.paused || lessonVideo.ended) {
                    lessonVideo.play();
                    playPauseBtn.innerHTML = '⏸️'; // Pause symbol
                } else {
                    lessonVideo.pause();
                    playPauseBtn.innerHTML = '▶️'; // Play symbol
                }
            });
        }

        // Rewind 10 seconds
        if (rewindBtn) {
            rewindBtn.addEventListener('click', () => {
                lessonVideo.currentTime = Math.max(0, lessonVideo.currentTime - 10);
            });
        }

        // Mark lesson completed when video finishes playing
        lessonVideo.addEventListener('ended', () => {
            markLessonCompleted();
        });
    }

    function markLessonCompleted() {
        const lessonId = document.getElementById('lesson_id')?.value;
        if (!lessonId) return;

        // Quietly update progress state in background via AJAX
        fetch('update_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lesson_id=${lessonId}&status=completed`
        })
            .then(response => response.json())
            .catch(err => console.error("Progress update failed:", err));
    }
});

// -------------------------------------------------------------------------
// 3. Interactive Quiz Modal Logic
// -------------------------------------------------------------------------
let quizQuestions = [];
let currentQuestionIndex = 0;
let quizScore = 0;

function startQuiz(quizId, questionsData) {
    quizQuestions = questionsData;
    currentQuestionIndex = 0;
    quizScore = 0;

    // Reset views
    document.getElementById('quiz_overlay').style.display = 'flex';
    document.getElementById('quiz_play_state').style.display = 'block';
    document.getElementById('quiz_reward_state').style.display = 'none';

    showQuestion();
}

function closeQuiz() {
    document.getElementById('quiz_overlay').style.display = 'none';
}

function showQuestion() {
    if (currentQuestionIndex >= quizQuestions.length) {
        completeQuiz();
        return;
    }

    const question = quizQuestions[currentQuestionIndex];

    // Update progress numbers
    document.getElementById('quiz_progress').innerText = `Question ${currentQuestionIndex + 1} of ${quizQuestions.length}`;
    document.getElementById('quiz_question').innerText = question.question;

    // Clear feedback text
    const feedbackBox = document.getElementById('quiz_feedback');
    feedbackBox.innerText = "";
    feedbackBox.className = "quiz-feedback";

    // Draw choices
    const optionsBox = document.getElementById('quiz_options');
    optionsBox.innerHTML = "";

    question.choices.forEach(choice => {
        const btn = document.createElement('button');
        btn.className = 'option-btn';
        btn.innerText = choice;
        btn.addEventListener('click', () => selectAnswer(btn, choice, question.answer));
        optionsBox.appendChild(btn);
    });
}

function selectAnswer(selectedBtn, chosenValue, correctAnswer) {
    // Disable all options immediately to prevent double-clicks
    const buttons = document.querySelectorAll('.option-btn');
    buttons.forEach(btn => btn.disabled = true);

    const feedbackBox = document.getElementById('quiz_feedback');

    if (chosenValue === correctAnswer) {
        selectedBtn.classList.add('correct');
        feedbackBox.innerText = "🌟 AWESOME! That's correct! 🌟";
        feedbackBox.className = "quiz-feedback feedback-correct";

        // Play synthesized sound effect
        if (window.sfx) sfx.playSuccess();

        quizScore += 5; // e.g. 5 points per question
    } else {
        selectedBtn.classList.add('wrong');
        feedbackBox.innerText = "❌ Uh oh! Give it another try next time! ❌";
        feedbackBox.className = "quiz-feedback feedback-wrong";

        // Play error synthesized sound effect
        if (window.sfx) sfx.playError();

        // Highlight the correct answer
        buttons.forEach(btn => {
            if (btn.innerText === correctAnswer) {
                btn.classList.add('correct');
            }
        });
    }

    // Move to next question after short delay
    setTimeout(() => {
        currentQuestionIndex++;
        showQuestion();
    }, 1800);
}

function completeQuiz() {
    const lessonId = document.getElementById('lesson_id').value;

    // Submit scores to backend
    fetch('save_score.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `lesson_id=${lessonId}&score=${quizScore}&max_score=${quizQuestions.length * 5}`
    })
        .then(response => response.json())
        .then(data => {
            // Display Reward summary
            document.getElementById('quiz_play_state').style.display = 'none';
            document.getElementById('quiz_reward_state').style.display = 'flex';

            document.getElementById('reward_score_text').innerText = `You scored ${quizScore} / ${quizQuestions.length * 5} points!`;
            document.getElementById('reward_xp_text').innerText = `+${data.xp_earned} Gold Stars Earned!`;

            // If they have streak increase details, it can be printed here too
            if (data.new_streak) {
                document.getElementById('reward_xp_text').innerHTML += `<br>🔥 Streak increases to ${data.new_streak} days!`;
            }
        })
        .catch(err => {
            console.error("Score submission error:", err);
            // Fallback display
            document.getElementById('quiz_play_state').style.display = 'none';
            document.getElementById('quiz_reward_state').style.display = 'flex';
            document.getElementById('reward_score_text').innerText = `You finished the quiz!`;
        });
}
