import { registerGameLiveViewer } from './gameLiveViewer.js';

// Fallback gdy skrypt załadowany osobno (np. stary cache); główna rejestracja jest w app.js.
if (window.Alpine) {
    registerGameLiveViewer(window.Alpine);
}
