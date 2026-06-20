/**
 * audio.js
 * Synthesizes playful game sound effects using the HTML5 Web Audio API.
 * Eliminates external file dependencies and ensures instant audio response.
 */

class GameAudio {
    constructor() {
        this.ctx = null;
    }

    /**
     * Initializes the AudioContext on user interaction to comply with browser autoplay policies.
     */
    initContext() {
        if (!this.ctx) {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    /**
     * Plays a cheerful, ascending success fanfare.
     */
    playSuccess() {
        try {
            this.initContext();
            const now = this.ctx.currentTime;
            
            // Ascending C-Major chord notes: C5 (523.25Hz), E5 (659.25Hz), G5 (783.99Hz), C6 (1046.50Hz)
            const notes = [523.25, 659.25, 783.99, 1046.50];
            const noteDuration = 0.12;
            const overlap = 0.03;

            notes.forEach((freq, idx) => {
                const startTime = now + idx * (noteDuration - overlap);
                
                // Create oscillator and gain node
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                
                // Bubbly sound - triangle wave is soft and woodwind-like
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, startTime);
                
                // Envelope
                gain.gain.setValueAtTime(0, startTime);
                gain.gain.linearRampToValueAtTime(0.3, startTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + noteDuration);
                
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                
                osc.start(startTime);
                osc.stop(startTime + noteDuration);
            });
        } catch (e) {
            console.warn("Audio Context error:", e);
        }
    }

    /**
     * Plays a cartoony, low-pitched buzzer sound for incorrect attempts.
     */
    playError() {
        try {
            this.initContext();
            const now = this.ctx.currentTime;
            
            // Create two oscillators for a slightly dissonant, buzzier texture
            const osc1 = this.ctx.createOscillator();
            const osc2 = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            
            osc1.type = 'sawtooth';
            osc1.frequency.setValueAtTime(130.81, now); // C3
            // Slide frequency downwards slightly for comedic "wah-wah" effect
            osc1.frequency.exponentialRampToValueAtTime(80, now + 0.35);

            osc2.type = 'square';
            osc2.frequency.setValueAtTime(135, now); // Slightly detuned
            osc2.frequency.exponentialRampToValueAtTime(85, now + 0.35);
            
            // Envelope
            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.25, now + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            
            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(this.ctx.destination);
            
            osc1.start(now);
            osc2.start(now);
            
            osc1.stop(now + 0.35);
            osc2.stop(now + 0.35);
        } catch (e) {
            console.warn("Audio Context error:", e);
        }
    }
}

// Instantiate global sound system instance
const sfx = new GameAudio();
// Initialize audio context on first screen tap or click
window.addEventListener('click', () => sfx.initContext(), { once: true });
window.addEventListener('touchstart', () => sfx.initContext(), { once: true });
