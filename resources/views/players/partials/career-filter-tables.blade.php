{{-- Szczegóły kariery: te same filtry źródła i okna co kafelki powyżej. --}}
<section>
    <h2 class="text-base font-bold text-accent mb-2.5">Szczegóły</h2>
    <div class="career-panel">
        <dl class="career-highlight-grid">
            <div class="overview-form-stat">
                <dt>Mecze</dt>
                <dd x-text="table.games ?? 0"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>Średnia 3 lotki</dt>
                <dd x-text="formatAverage(table.avg_three_darts)"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>Najwyższy checkout</dt>
                <dd x-text="dash(table.highest_hf)"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>Najszybsza lotka</dt>
                <dd x-text="qf(table.fastest_qf)"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>180</dt>
                <dd x-text="table.count_max ?? 0"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>170+</dt>
                <dd x-text="table.count_170_plus ?? 0"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>Checkout 100+</dt>
                <dd x-text="table.count_hf ?? 0"></dd>
            </div>
            <div class="overview-form-stat">
                <dt>Szybkie lotki</dt>
                <dd x-text="table.count_qf ?? 0"></dd>
            </div>
        </dl>
    </div>
</section>
