import './bootstrap';
import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';

registerGameLiveViewer(alpine);

window.Alpine = alpine;
alpine.start();
