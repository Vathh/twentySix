import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';
import './gameOverlayTicker.js';

registerGameLiveViewer(alpine);

window.Alpine = alpine;
alpine.start();
