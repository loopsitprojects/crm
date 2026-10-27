@if(\App\Models\Setting::isHalloweenTheme())
<!-- Spooky AI Vision Corner Peeking Ghost Overlay -->
<div x-data="halloweenCornerGhost()"
     x-init="initGhost()"
     class="fixed inset-0 pointer-events-none z-[60] overflow-hidden select-none"
     aria-hidden="true">

    <!-- Top-Left Ghost -->
    <div class="absolute top-0 left-0 transform-gpu pointer-events-none ghost-corner-container"
         :class="{
             'ghost-corner-tl-active': currentCorner === 0 && (stage === 'entering' || stage === 'peeking'),
             'ghost-corner-tl-hidden': currentCorner !== 0 || stage === 'hidden' || stage === 'leaving'
         }">
        <img src="{{ asset('images/halloween/realistic-ghost.png') }}"
             class="w-36 sm:w-48 md:w-56 lg:w-64 h-auto select-none pointer-events-none filter drop-shadow-[0_0_22px_rgba(0,210,255,0.85)] drop-shadow-[0_0_45px_rgba(147,51,234,0.55)]"
             :class="{ 'animate-ghost-corner-float-tl': stage === 'peeking' }"
             alt="Halloween Ghost">
    </div>

    <!-- Top-Right Ghost -->
    <div class="absolute top-0 right-0 transform-gpu pointer-events-none ghost-corner-container"
         :class="{
             'ghost-corner-tr-active': currentCorner === 1 && (stage === 'entering' || stage === 'peeking'),
             'ghost-corner-tr-hidden': currentCorner !== 1 || stage === 'hidden' || stage === 'leaving'
         }">
        <img src="{{ asset('images/halloween/realistic-ghost.png') }}"
             class="w-36 sm:w-48 md:w-56 lg:w-64 h-auto select-none pointer-events-none filter drop-shadow-[0_0_22px_rgba(0,210,255,0.85)] drop-shadow-[0_0_45px_rgba(147,51,234,0.55)]"
             :class="{ 'animate-ghost-corner-float-tr': stage === 'peeking' }"
             alt="Halloween Ghost">
    </div>

    <!-- Bottom-Right Ghost -->
    <div class="absolute bottom-0 right-0 transform-gpu pointer-events-none ghost-corner-container"
         :class="{
             'ghost-corner-br-active': currentCorner === 2 && (stage === 'entering' || stage === 'peeking'),
             'ghost-corner-br-hidden': currentCorner !== 2 || stage === 'hidden' || stage === 'leaving'
         }">
        <img src="{{ asset('images/halloween/realistic-ghost.png') }}"
             class="w-36 sm:w-48 md:w-56 lg:w-64 h-auto select-none pointer-events-none filter drop-shadow-[0_0_22px_rgba(0,210,255,0.85)] drop-shadow-[0_0_45px_rgba(147,51,234,0.55)]"
             :class="{ 'animate-ghost-corner-float-br': stage === 'peeking' }"
             alt="Halloween Ghost">
    </div>

    <!-- Bottom-Left Ghost -->
    <div class="absolute bottom-0 left-0 transform-gpu pointer-events-none ghost-corner-container"
         :class="{
             'ghost-corner-bl-active': currentCorner === 3 && (stage === 'entering' || stage === 'peeking'),
             'ghost-corner-bl-hidden': currentCorner !== 3 || stage === 'hidden' || stage === 'leaving'
         }">
        <img src="{{ asset('images/halloween/realistic-ghost.png') }}"
             class="w-36 sm:w-48 md:w-56 lg:w-64 h-auto select-none pointer-events-none filter drop-shadow-[0_0_22px_rgba(0,210,255,0.85)] drop-shadow-[0_0_45px_rgba(147,51,234,0.55)]"
             :class="{ 'animate-ghost-corner-float-bl': stage === 'peeking' }"
             alt="Halloween Ghost">
    </div>
</div>

<style>
    .ghost-corner-container {
        transition: transform 1.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 1.1s ease-out;
        will-change: transform, opacity;
    }

    /* Top-Left Corner */
    .ghost-corner-tl-hidden {
        transform: translate(-115%, -115%) rotate(-35deg) scale(0.65);
        opacity: 0;
    }
    .ghost-corner-tl-active {
        transform: translate(-10%, -10%) rotate(14deg) scale(1);
        opacity: 0.95;
    }
    @keyframes ghostFloatTL {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg) scale(1);
        }
        50% {
            transform: translate(6px, 8px) rotate(3deg) scale(1.05);
        }
    }
    .animate-ghost-corner-float-tl {
        animation: ghostFloatTL 2.5s ease-in-out infinite;
    }

    /* Top-Right Corner */
    .ghost-corner-tr-hidden {
        transform: translate(115%, -115%) rotate(35deg) scale(0.65);
        opacity: 0;
    }
    .ghost-corner-tr-active {
        transform: translate(10%, -10%) rotate(-14deg) scale(1);
        opacity: 0.95;
    }
    @keyframes ghostFloatTR {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg) scale(1);
        }
        50% {
            transform: translate(-6px, 8px) rotate(-3deg) scale(1.05);
        }
    }
    .animate-ghost-corner-float-tr {
        animation: ghostFloatTR 2.5s ease-in-out infinite;
    }

    /* Bottom-Right Corner */
    .ghost-corner-br-hidden {
        transform: translate(115%, 115%) rotate(35deg) scale(0.65);
        opacity: 0;
    }
    .ghost-corner-br-active {
        transform: translate(10%, 10%) rotate(-10deg) scale(1);
        opacity: 0.95;
    }
    @keyframes ghostFloatBR {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg) scale(1);
        }
        50% {
            transform: translate(-6px, -8px) rotate(-2deg) scale(1.05);
        }
    }
    .animate-ghost-corner-float-br {
        animation: ghostFloatBR 2.5s ease-in-out infinite;
    }

    /* Bottom-Left Corner */
    .ghost-corner-bl-hidden {
        transform: translate(-115%, 115%) rotate(-35deg) scale(0.65);
        opacity: 0;
    }
    .ghost-corner-bl-active {
        transform: translate(-10%, 10%) rotate(10deg) scale(1);
        opacity: 0.95;
    }
    @keyframes ghostFloatBL {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg) scale(1);
        }
        50% {
            transform: translate(6px, -8px) rotate(2deg) scale(1.05);
        }
    }
    .animate-ghost-corner-float-bl {
        animation: ghostFloatBL 2.5s ease-in-out infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        .ghost-corner-container {
            display: none !important;
        }
    }
</style>

<script>
    function halloweenCornerGhost() {
        return {
            currentCorner: 0, // 0: Top-Left, 1: Top-Right, 2: Bottom-Right, 3: Bottom-Left
            stage: 'hidden',  // 'hidden', 'entering', 'peeking', 'leaving'
            timer: null,

            initGhost() {
                // Initial surprise peek after page load: 2.5s
                this.scheduleNext(2500);
            },

            scheduleNext(delay) {
                if (this.timer) clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    this.startPeek();
                }, delay);
            },

            startPeek() {
                // 1. Ghost glides smoothly into the corner
                this.stage = 'entering';

                // 2. Once in position, start gentle floating animation
                setTimeout(() => {
                    if (this.stage === 'entering') {
                        this.stage = 'peeking';
                    }
                }, 1250);

                // 3. Stay in corner for ~3.5s, then retreat back into darkness
                setTimeout(() => {
                    this.stage = 'leaving';

                    // 4. Once fully out of view, switch corner and wait time gap (6 to 9 seconds)
                    setTimeout(() => {
                        this.stage = 'hidden';
                        this.currentCorner = (this.currentCorner + 1) % 4;
                        const timeGap = 6000 + Math.floor(Math.random() * 3000);
                        this.scheduleNext(timeGap);
                    }, 1250);
                }, 4800);
            }
        };
    }
</script>
@endif
