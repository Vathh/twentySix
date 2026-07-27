import './bootstrap';
import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';
import { registerTournamentGroupsLive } from './tournamentGroupsLive.js';
import { registerIndexLoadMore } from './indexLoadMore.js';

registerGameLiveViewer(alpine);
registerTournamentGroupsLive(alpine);
registerIndexLoadMore(alpine);

window.Alpine = alpine;
alpine.start();
