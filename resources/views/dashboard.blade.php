<x-layouts.app title="Dashboard">
    <x-page-header :title="'Assalam-o-Alaikum, '.Str::before($user->name, ' ')" :subtitle="now()->format('l, j F Y')" />
</x-layouts.app>
