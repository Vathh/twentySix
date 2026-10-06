<aside class="admin-sidebar">
    <h2 class="admin-sidebar-title">Ustawienia</h2>

    <nav class="flex flex-col space-y-3">
        <a href="{{ route('settings.password.edit') }}"
           class="admin-sidebar-link {{ request()->routeIs('settings.password.*') ? '!bg-bg-elevated !border-border text-accent' : '' }}">
            Zmień hasło
        </a>
    </nav>
</aside>
