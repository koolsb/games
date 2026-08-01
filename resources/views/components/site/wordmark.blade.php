{{--
    The two-tone lettering for each game, and the hub's own mark. Kept as a
    component rather than config because the color spans are markup.
--}}
@props(['game' => null])

@switch($game)
    @case('qwixx')
        Qwi<span class="text-qwixx-red">x</span><span class="text-qwixx-blue">x</span>
        @break

    @case('phase10')
        Phase<span class="text-phase-yellow">10</span>
        @break

    @default
        Games
@endswitch
