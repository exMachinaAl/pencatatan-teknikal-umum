@php
    $record = $getRecord();
    $compact = $compact ?? false;
    $links = \App\Support\DocumentAttachmentLink::links(
        $record,
        $externalAttribute ? data_get($record, $externalAttribute) : null,
    );
@endphp

@if ($links !== [])
    <div @class(['tu-document-links', 'tu-document-links--compact' => $compact])>
        @foreach ($links as $link)
            <a
                href="{{ $link['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                title="{{ $link['title'] }}"
                @class(['tu-document-link', 'tu-document-link--compact' => $compact])
                aria-label="{{ $link['label'] }}"
            >
                <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                <span @class(['sr-only' => $compact])>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>
@endif
