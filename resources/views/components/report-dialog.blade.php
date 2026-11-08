<div
    x-data="reportDialog()"
    @open-report.window="open($event.detail)"
    @keydown.escape.window="visible && close()"
>
    <template x-teleport="body">
        <div
            x-show="visible"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="close()"
        >
            <div class="w-full max-w-md rounded-xl border border-border bg-bg-deep p-6" role="dialog" aria-modal="true" @click.stop>
                <template x-if="saved">
                    <div>
                        <h2 class="text-lg font-semibold text-accent mb-2">Zgłoszenie zapisane</h2>
                        <p class="text-sm text-text-secondary mb-5">Dziękujemy. Zajmiemy się tym zgłoszeniem.</p>
                        <button type="button" class="btn btn-primary w-full" @click="close()">Zamknij</button>
                    </div>
                </template>
                <template x-if="!saved">
                    <div>
                        <h2 class="text-lg font-semibold text-accent mb-1" x-text="title"></h2>
                        <p class="text-sm text-text-secondary mb-4">Zaznacz jeden powód. Komentarz możesz dopisać, jeśli chcesz coś dodać.</p>
                        <fieldset class="mb-4 space-y-2">
                            <legend class="form-label">Dlaczego zgłaszasz?</legend>
                            <label class="flex items-center gap-2 text-sm text-text">
                                <input type="radio" name="report-reason" value="spam" x-model="reason">
                                Spam
                            </label>
                            <label class="flex items-center gap-2 text-sm text-text">
                                <input type="radio" name="report-reason" value="insult" x-model="reason">
                                Obraza
                            </label>
                            <label class="flex items-center gap-2 text-sm text-text">
                                <input type="radio" name="report-reason" value="other" x-model="reason">
                                Inne
                            </label>
                        </fieldset>
                        <label class="block mb-4">
                            <span class="form-label">Komentarz</span>
                            <textarea
                                class="input-field"
                                style="min-height: 6rem"
                                maxlength="500"
                                x-model="details"
                                placeholder="Możesz dopisać, co się stało."
                            ></textarea>
                        </label>
                        <p class="text-sm text-danger mb-3" x-show="error" x-cloak x-text="error"></p>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button type="button" class="btn btn-primary flex-1" @click="submit()" :disabled="sending">Wyślij zgłoszenie</button>
                            <button type="button" class="btn btn-secondary flex-1" @click="close()" :disabled="sending">Anuluj</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
