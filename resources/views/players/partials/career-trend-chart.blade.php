<div
    class="career-chart-plot"
    x-show="{{ $chartVar }}"
    :class="{ 'is-hot': chartTip && chartTip.id === '{{ $chartVar }}' }"
    @pointermove="moveChartTip($event, {{ $chartVar }}, '{{ $chartVar }}')"
    @pointerleave="hideChartTip('{{ $chartVar }}')"
>
    <div x-html="{{ $htmlVar }}"></div>
    <template x-if="chartTip && chartTip.id === '{{ $chartVar }}'">
        <span class="career-chart-dot" :style="chartTip.dotStyle"></span>
    </template>
    <template x-if="chartTip && chartTip.id === '{{ $chartVar }}'">
        <div class="career-chart-tip" :style="chartTip.tipStyle">
            <p class="career-chart-tip-value" x-text="chartTip.valueLabel"></p>
            <p class="career-chart-tip-date" x-show="chartTip.dateLabel" x-text="chartTip.dateLabel"></p>
        </div>
    </template>
</div>
<p class="text-sm text-text-muted" x-show="!{{ $chartVar }}">{{ $emptyText }}</p>
