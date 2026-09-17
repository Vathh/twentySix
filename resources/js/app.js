import './bootstrap';
import alpine from 'alpinejs';
import { registerGameLiveViewer } from './gameLiveViewer.js';
import { registerFfaLiveViewer } from './ffaLiveViewer.js';
import { registerTournamentGroupsLive } from './tournamentGroupsLive.js';
import { registerTournamentPlayoffLive } from './tournamentPlayoffLive.js';
import { registerTournamentPageLive } from './tournamentPageLive.js';
import { registerTournamentJoinRequestsLive } from './tournamentJoinRequestsLive.js';
import { registerIndexLoadMore } from './indexLoadMore.js';
import { registerRefereeLogin } from './referee/refereeLogin.js';
import { registerRefereeGames } from './referee/refereeGames.js';
import { registerRefereeScoring } from './referee/refereeScoring.js';
import { registerLeagueRosterBoard } from './leagueRosterBoard.js';
import { registerRelatedUserSearch } from './relatedUserSearch.js';
import { registerCheckoutWheels } from './checkoutWheel.js';
import { registerFriendsPanel } from './friendsPanel.js';
import { registerPlayerCareer } from './playerCareer.js';

registerGameLiveViewer(alpine);
registerFfaLiveViewer(alpine);
registerTournamentGroupsLive(alpine);
registerTournamentPlayoffLive(alpine);
registerTournamentPageLive(alpine);
registerTournamentJoinRequestsLive(alpine);
registerIndexLoadMore(alpine);
registerRefereeLogin(alpine);
registerRefereeGames(alpine);
registerRefereeScoring(alpine);
registerLeagueRosterBoard(alpine);
registerRelatedUserSearch(alpine);
registerCheckoutWheels();
registerFriendsPanel(alpine);
registerPlayerCareer(alpine);

function registerSiteHeaderOffset() {
    const header = document.querySelector('.site-header');
    if (!header) {
        return;
    }
    const sync = () => {
        document.documentElement.style.setProperty('--site-header-h', `${header.offsetHeight}px`);
    };
    sync();
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(sync).observe(header);
    }
    window.addEventListener('resize', sync);
}

registerSiteHeaderOffset();

window.Alpine = alpine;
alpine.start();
