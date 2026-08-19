@props([
    'messages' => [],
    'duration' => 4000
])

<div
    x-data="{
        toasts: {{ json_encode($messages) }}.map((toast, index) => ({ ...toast, id: 'flash-' + index })),
        push(detail) {
            if (!detail?.text) {
                return;
            }
            this.toasts.push({
                id: Date.now() + '-' + Math.random(),
                type: detail.type || 'error',
                text: detail.text,
                show: true,
                duration: detail.duration,
            });
        },
    }"
    @app-toast.window="push($event.detail)"
    class="fixed top-5 right-5 z-50 space-y-2 w-96 max-w-[calc(100vw-2rem)]"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.show !== false"
            x-init="setTimeout(() => toast.show = false, toast.duration ?? {{ $duration }})"
            x-transition
            :class="{
                'alert-success': toast.type === 'success',
                'alert-danger': toast.type === 'error',
                'alert-info': toast.type === 'info',
                'alert-warning': toast.type === 'warning'
            }"
            class="flex justify-between items-center gap-3"
        >
            <div x-text="toast.text"></div>
            <button @click="toast.show = false" class="shrink-0 font-bold opacity-70 hover:opacity-100">&times;</button>
        </div>
    </template>
</div>
