import {
    clearRefereeSession,
    loadRefereeSession,
    saveRefereeSession,
} from './session.js';
import { refereeFetch } from './api.js';

export function registerRefereeLogin(Alpine) {
    Alpine.data('refereeLogin', (config) => ({
        code: config.initialCode ?? '',
        busy: false,
        error: '',

        init() {
            const existing = loadRefereeSession();
            if (existing && !config.initialCode) {
                window.location.replace(config.gamesUrl);
                return;
            }
            if (config.autoSubmit && this.code.trim().length >= 4) {
                this.$nextTick(() => this.submit());
            }
        },

        async submit() {
            if (this.busy) {
                return;
            }
            const code = this.code.trim().toUpperCase();
            if (code.length < 4) {
                this.error = 'Wpisz kod sędziowski.';
                return;
            }

            this.busy = true;
            this.error = '';
            try {
                const data = await refereeFetch(config.loginUrl, {
                    method: 'POST',
                    body: { code },
                });
                saveRefereeSession({
                    token: data.token,
                    tournamentId: data.tournamentId,
                });
                window.location.assign(config.gamesUrl);
            } catch (e) {
                this.error = e.message || 'Nie udało się zalogować.';
                clearRefereeSession();
            } finally {
                this.busy = false;
            }
        },
    }));
}
