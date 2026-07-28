import './bootstrap';
import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';
import { registerTournamentGroupsLive } from './tournamentGroupsLive.js';
import { registerTournamentJoinRequestsLive } from './tournamentJoinRequestsLive.js';
import { registerIndexLoadMore } from './indexLoadMore.js';

registerGameLiveViewer(alpine);
registerTournamentGroupsLive(alpine);
registerTournamentJoinRequestsLive(alpine);
registerIndexLoadMore(alpine);

window.Alpine = alpine;
alpine.start();
