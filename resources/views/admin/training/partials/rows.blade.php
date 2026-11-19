<section class="mb-8">
    <h2 class="text-accent font-semibold mb-3">{{ $heading }}</h2>
    @if($rows->isEmpty())
        <p class="text-text-muted text-sm">Brak.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm text-text-secondary">
                <thead class="bg-bg text-accent">
                    <tr>
                        <th class="text-left py-3 px-3 font-semibold">Nazwa</th>
                        <th class="text-left py-3 px-3 font-semibold">Szczegóły</th>
                        <th class="text-left py-3 px-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $definition)
                        <tr class="border-t border-border/60">
                            <td class="py-3 px-3">
                                <a href="{{ route('admin.training.show', $definition) }}" class="flex items-center gap-3 text-text hover:text-accent">
                                    <x-player-avatar :name="$definition->name" :avatar-url="$definition->imageUrl()" size="sm" />
                                    <span>{{ $definition->name }}</span>
                                </a>
                            </td>
                            <td class="py-3 px-3">
                                @include('admin.training.partials.summary', ['definition' => $definition])
                            </td>
                            <td class="py-3 px-3">{{ $definition->status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
