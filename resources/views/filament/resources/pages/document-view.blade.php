<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @if ($this->hasInfolist())
        {{ $this->infolist }}
    @else
        <div wire:key="{{ $this->getId() }}.forms.{{ $this->getFormStatePath() }}">
            {{ $this->form }}
        </div>
    @endif

    @include('filament.resources.partials.document-workflow', ['record' => $record])
</x-filament-panels::page>
