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
import { registerPlayerSearch } from './playerSearch.js';
import { registerCheckoutWheels } from './checkoutWheel.js';
import { registerFriendsPanel } from './friendsPanel.js';
import { registerFeedPost } from './feedPost.js';
import { registerPlayerCareer } from './playerCareer.js';
import { registerPlayerAvatarCrop } from './playerAvatarCrop.js';

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
registerPlayerSearch(alpine);
registerCheckoutWheels();
registerFriendsPanel(alpine);
registerFeedPost(alpine);
registerPlayerCareer(alpine);
registerPlayerAvatarCrop(alpine);

function registerSiteChromeOffsets() {
    const header = document.querySelector('.site-header');
    const footer = document.querySelector('.site-footer');
    const sync = () => {
        if (header) {
            document.documentElement.style.setProperty('--site-header-h', `${header.offsetHeight}px`);
        }
        if (footer) {
            document.documentElement.style.setProperty('--site-footer-h', `${footer.offsetHeight}px`);
        }
    };
    sync();
    if (typeof ResizeObserver !== 'undefined') {
        if (header) {
            new ResizeObserver(sync).observe(header);
        }
        if (footer) {
            new ResizeObserver(sync).observe(footer);
        }
    }
    window.addEventListener('resize', sync);
}

registerSiteChromeOffsets();

window.Alpine = alpine;
alpine.start();
