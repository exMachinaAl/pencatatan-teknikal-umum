@php
    $record = $getRecord();
    $links = \App\Support\DocumentAttachmentLink::links(
        $record,
        $externalAttribute ? data_get($record, $externalAttribute) : null,
    );
@endphp

<div class="tu-mobile-record">
    <div class="tu-mobile-record-copy">
        <div class="tu-mobile-record-title">{{ $title }}</div>
        <div class="tu-mobile-record-meta">{{ $meta }}</div>
    </div>

    @if ($links !== [])
        <div class="tu-document-links tu-document-links--compact">
            @foreach ($links as $link)
                <a
                    href="{{ $link['url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="{{ $link['title'] }}"
                    aria-label="{{ $link['label'] }}"
                    class="tu-document-link tu-document-link--compact"
                >
                    <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                    <span class="sr-only">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
