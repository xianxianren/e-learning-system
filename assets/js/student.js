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

