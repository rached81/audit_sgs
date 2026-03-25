<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - Audit SGS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Connexion</h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Connectez-vous pour accéder à l'application
            </p>
        </div>
        <form class="mt-8 space-y-6" action="{{ route('login') }}" method="POST">
            @csrf
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="matricule" class="sr-only">Matricule</label>
                    <input id="matricule" name="matricule" type="text" required class="appearance-none rounded-none rounded-t-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Matricule" value="{{ old('matricule') }}">
                </div>
                <div>
                    <label for="password" class="sr-only">Mot de passe</label>
                    <input id="password" name="password" type="password" required class="appearance-none rounded-none rounded-b-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Mot de passe">
                </div>
            </div>

            @if ($errors->any())
                <div class="text-red-500 text-sm text-center">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Se connecter
                </button>
            </div>
        </form>
    </div>

    <script>
        // Safety net: if a fullscreen overlay/backdrop is left/injected,
        // it can block clicks on the login form. Neutralize it on /login.
        (function () {
            function closeDialogs() {
                for (const d of document.querySelectorAll('dialog[open]')) {
                    try { d.close(); } catch (e) { /* no-op */ }
                }
            }

            function isBlockingFullscreenOverlay(el) {
                if (!(el instanceof HTMLElement)) return false;
                if (el.id === 'loadingOverlay') return true;
                if (el.classList.contains('modal-backdrop')) return true;
                if (el.classList.contains('overlay')) return true;
                if (el.classList.contains('backdrop')) return true;

                const cs = window.getComputedStyle(el);
                if (cs.position !== 'fixed') return false;

                // Covers the viewport (common overlay pattern)
                const covers =
                    (cs.top === '0px' || cs.inset === '0px') &&
                    (cs.left === '0px' || cs.inset === '0px') &&
                    (cs.right === '0px' || cs.inset === '0px') &&
                    (cs.bottom === '0px' || cs.inset === '0px');

                if (!covers) return false;

                // Blocks interaction
                const blocks = cs.pointerEvents !== 'none' && cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
                if (!blocks) return false;

                // Above typical page content (Tailwind overlays often z-50)
                const z = Number.parseInt(cs.zIndex || '0', 10);
                if (Number.isFinite(z) && z >= 40) return true;

                // If z-index is 'auto' but background is not fully transparent, still suspicious
                const bg = cs.backgroundColor || '';
                if (bg && !bg.includes('rgba(0, 0, 0, 0)') && bg !== 'transparent') return true;

                return false;
            }

            function removeBlockingOverlays(root = document) {
                closeDialogs();

                const candidates = [
                    root.getElementById?.('loadingOverlay'),
                    ...root.querySelectorAll?.('.modal-backdrop, .overlay, .backdrop') || [],
                ].filter(Boolean);

                for (const el of candidates) {
                    try { el.remove(); } catch (e) { /* no-op */ }
                }

                // Also remove any generic fullscreen fixed overlay that blocks clicks
                for (const el of document.querySelectorAll('body *')) {
                    if (isBlockingFullscreenOverlay(el)) {
                        try { el.remove(); } catch (e) { /* no-op */ }
                    }
                }

                // Unblock scroll/clicks if something modified them
                document.documentElement.style.pointerEvents = '';
                document.body.style.pointerEvents = '';
                document.body.style.overflow = '';
            }

            try {
                removeBlockingOverlays(document);

                // Watch for overlays injected after load (rare, but matches your symptom).
                const obs = new MutationObserver((mutations) => {
                    for (const m of mutations) {
                        for (const node of m.addedNodes || []) {
                            if (node instanceof HTMLElement) {
                                if (isBlockingFullscreenOverlay(node)) {
                                    try { node.remove(); } catch (e) { /* no-op */ }
                                }
                            }
                        }
                    }
                });
                obs.observe(document.documentElement, { childList: true, subtree: true });

                // Extra pass after first paint
                requestAnimationFrame(() => removeBlockingOverlays(document));
                setTimeout(() => removeBlockingOverlays(document), 250);
            } catch (e) {
                // no-op
            }
        })();
    </script>
</body>
</html>
