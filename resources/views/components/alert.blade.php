@props([
    'messages' => [],  // ['type' => 'success', 'text' => 'Liga dodana']
    'duration' => 4000
])

<div
    x-data="{ toasts: {{ json_encode($messages) }} }"
    class="fixed top-5 right-5 z-50 space-y-2 w-96"
>
    <template x-for="(toast, index) in toasts" :key="index">
        <div
            x-show="toast.show !== false"
            x-init="setTimeout(() => toast.show = false, toast.duration ?? {{ $duration }})"
            x-transition
            :class="{
                'bg-green-500 text-white': toast.type === 'success',
                'bg-red-500 text-white': toast.type === 'error',
                'bg-blue-500 text-white': toast.type === 'info',
                'bg-yellow-500 text-black': toast.type === 'warning'
            }"
            class="p-4 rounded shadow-lg flex justify-between items-center"
        >
            <div x-text="toast.text"></div>
            <button @click="toast.show = false" class="ml-4 font-bold">&times;</button>
        </div>
    </template>
</div>
