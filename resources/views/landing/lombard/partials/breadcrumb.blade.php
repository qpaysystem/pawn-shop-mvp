@if(!empty($items))
<nav class="breadcrumb-capital" aria-label="Хлебные крошки">
    @foreach($items as $i => $item)
        @if($i > 0)<span class="sep">→</span>@endif
        @if(is_array($item))
            @if(!empty($item[1]))
                <a href="{{ $item[1] }}">{{ $item[0] }}</a>
            @else
                <span>{{ $item[0] }}</span>
            @endif
        @else
            <span>{{ $item }}</span>
        @endif
    @endforeach
</nav>
@endif
