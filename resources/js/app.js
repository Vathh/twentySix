import './bootstrap';
import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';
import { registerFfaLiveViewer } from './ffaLiveViewer.js';
import { registerTournamentGroupsLive } from './tournamentGroupsLive.js';
import { registerTournamentJoinRequestsLive } from './tournamentJoinRequestsLive.js';
import { registerIndexLoadMore } from './indexLoadMore.js';
import { registerRefereeLogin } from './referee/refereeLogin.js';
import { registerRefereeGames } from './referee/refereeGames.js';
import { registerRefereeScoring } from './referee/refereeScoring.js';

registerGameLiveViewer(alpine);
registerFfaLiveViewer(alpine);
registerTournamentGroupsLive(alpine);
registerTournamentJoinRequestsLive(alpine);
registerIndexLoadMore(alpine);
registerRefereeLogin(alpine);
registerRefereeGames(alpine);
registerRefereeScoring(alpine);

window.Alpine = alpine;
alpine.start();
