@extends('layouts.app')

@section('content')
    <!-- Bannière succès (affichée après fermeture du modal) -->
    <div id="importSuccessBanner" class="hidden mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3 text-center font-semibold" role="status"></div>

    <!-- Loading Overlay (sous la barre d'annulation fixe) -->
        <div id="loadingOverlay" class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center hidden overflow-y-auto py-8">
        <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md mx-4 w-full relative max-h-[min(90vh,40rem)] overflow-y-auto">
            <div class="absolute top-3 right-3 flex items-center gap-2 z-10">
                <button id="minimizeOverlayBtn" type="button"
                        class="h-9 w-9 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-extrabold leading-none"
                        aria-label="Réduire">
                    &minus;
                </button>
                <button id="closeOverlayBtn" type="button"
                        class="h-9 w-9 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-extrabold leading-none"
                        aria-label="Fermer">
                    &times;
                </button>
            </div>
            <div id="loadingSpinner" class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-16 w-16 mx-auto mb-4 border-indigo-600"></div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Import en cours...</h2>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-4 mb-1 relative overflow-hidden">
                <div id="progressBar" class="bg-indigo-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <div id="progressText" class="text-sm text-indigo-700 font-bold mb-4">0%</div>

            <p id="infoText" class="text-gray-600 mb-4">Veuillez patienter, ne fermez pas la page.</p>
            <p class="text-xs text-gray-500 mb-4">Suivi simplifié: une seule barre de progression.</p>

            <div id="timeEstimate" class="text-sm font-semibold text-indigo-600 bg-indigo-50 py-2 px-4 rounded">
                Initialisation...
            </div>

            <div class="mt-4 text-xs text-gray-500">
                Astuce: vous pouvez réduire le popup (−) et continuer à travailler.
            </div>
        </div>
    </div>

    <div id="importProgressConfig"
         data-run-id="{{ session('import_run_id') }}"
         data-table="{{ session('import_table') }}"
         data-status-url="{{ route('import.status', [], false) }}"
         data-login-url="{{ route('login', [], false) }}"
         data-consultation-url="{{ route('consultation.index', [], false) }}"
         class="hidden"></div>

    <!-- Sticky mini progress bar (shown when overlay is reduced) — au-dessus de l’overlay, sous la barre d’annulation -->
    <div id="importSticky"
         class="hidden fixed bottom-[7.5rem] left-1/2 -translate-x-1/2 z-[9998] w-[min(46rem,calc(100vw-2rem))] bg-white border border-gray-200 shadow-lg rounded-xl px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm font-bold text-gray-800 truncate">Import en cours</div>
                <div id="stickyText" class="text-xs text-gray-600 truncate">Initialisation…</div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                <button id="stickyOpenBtn" type="button" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold whitespace-nowrap">
                    Ouvrir
                </button>
                <button id="stickyDismissBtn" type="button" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold whitespace-nowrap">
                    Masquer
                </button>
            </div>
        </div>
        <div class="mt-2 w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div id="stickyBar" class="bg-indigo-600 h-2 transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>


    <h1 class="text-3xl font-bold mb-8 text-center text-indigo-700">Import des Stocks</h1>

    <!-- Messages -->
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow" role="alert">
            <p class="font-bold">Succès</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow" role="alert">
            <p class="font-bold">Erreur</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white shadow-xl rounded-lg overflow-hidden md:flex">
        <!-- Documentation Left Side -->
        <div class="bg-indigo-50 p-8 md:w-1/2 border-r border-gray-100">
            <h2 class="text-xl font-bold mb-4 text-indigo-800">Structure du Fichier</h2>
            <p class="text-sm text-gray-600 mb-4">Le fichier Excel doit contenir les colonnes suivantes (l'ordre n'est pas strict mais les noms doivent correspondre aux entêtes) :</p>

            <ul class="text-sm space-y-2">
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">ARTICLE</span>
                    <span class="text-gray-600">Code article (Ex: 8 chiffres)</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">DESIGNATION</span>
                    <span class="text-gray-600">Libellé de l'article</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">INITIAL</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">ENTREE</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">SORTIE</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">FINALE</span>
                </li>
                <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">PUMP</span>
                        <span class="text-gray-500 text-xs ml-1">(prix unitaire moyen pondéré)</span>
                </li>
                    <li class="flex items-start">
                    <span class="font-mono bg-gray-200 px-2 rounded mr-2 text-xs py-0.5">VALEUR</span>
                </li>
            </ul>

            <div class="mt-6 border-t pt-4 border-indigo-200">
                <p class="text-xs text-indigo-500">
                    <span class="font-bold">Note:</span> Les lignes "Total", "Groupe", ou vides seront automatiquement ignorées.
                </p>
            </div>
        </div>

        <!-- Form Right Side -->
        <div class="p-8 md:w-1/2">
            <form id="importForm" action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="annee" class="block text-gray-700 font-bold mb-2">Exercice</label>
                        <input type="number" name="annee" id="annee" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ex: 2025" required value="{{ old('annee', date('Y')) }}" min="2000" max="2100">
                    </div>
                    <div>
                        <label for="programme" class="block text-gray-700 font-bold mb-2">Programme</label>
                        <select name="programme" id="programme" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="EF" {{ old('programme') == 'EF' ? 'selected' : '' }}>EF (Etat Final)</option>
                            <option value="GD" {{ old('programme') == 'GD' ? 'selected' : '' }}>GD (Générateur Données)</option>
                        </select>
                    </div>
                    <div>
                        <label for="reseau" class="block text-gray-700 font-bold mb-2">Réseau</label>
                        <select name="reseau" id="reseau" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="BUS" {{ old('reseau') == 'BUS' ? 'selected' : '' }}>BUS</option>
                            <option value="FERRE" {{ old('reseau') == 'FERRE' ? 'selected' : '' }}>FERRÉ</option>
                        </select>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-6 -mt-4">Nom généré : <strong>RES_[PROGRAMME]_[RESEAU]_[ANNEE]</strong> (ex: RES_EF_BUS_2025). Si la table existe et contient des données, l'import sera bloqué.</p>

                <div class="mb-6">
                    <label for="file" class="block text-gray-700 font-bold mb-2">Fichier Excel</label>
                    <div class="relative z-0 border-2 border-dashed border-gray-300 rounded-lg p-6 hover:bg-gray-50 transition-colors text-center cursor-pointer" onclick="document.getElementById('file').click()">
                        <input type="file" name="file" id="file" class="hidden" accept=".xlsx,.xls,.csv" onchange="handleFileSelect(this)">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="mt-2 block text-sm font-medium text-gray-900" id="filename">Cliquez pour choisir un fichier</span>
                    </div>
                </div>

                <div class="mt-8">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:-translate-y-1">
                        Importer les Données
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const importForm = document.getElementById('importForm');
        const overlay = document.getElementById('loadingOverlay');
        const sticky = document.getElementById('importSticky');
        const cfg = document.getElementById('importProgressConfig');
        const routeStatus = cfg?.dataset?.statusUrl || '';
        const routeLogin = cfg?.dataset?.loginUrl || '/login';
        const routeConsultation = cfg?.dataset?.consultationUrl || '';
        const importTableFromSession = cfg?.dataset?.table || '';

        const ETA_HISTORY_KEY = 'import_eta_history_v2';
        let selectedFileSize = 0;
        let currentUploadTable = '';
        let currentUploadRunId = '';
        let pollTimer = null;
        let trackingStartAt = null;

        function importTypeFromTable(tableName) {
            const t = String(tableName || '').toUpperCase();
            if (t.includes('_EF_')) return 'EF';
            if (t.includes('_GD_')) return 'GD';
            return 'GENERIC';
        }
        function readHistory() { try { return JSON.parse(localStorage.getItem(ETA_HISTORY_KEY) || '[]'); } catch { return []; } }
        function saveHistory(items) { localStorage.setItem(ETA_HISTORY_KEY, JSON.stringify(items.slice(-40))); }
        function pushHistory(type, seconds) {
            const s = Number(seconds || 0);
            if (!Number.isFinite(s) || s < 5) return;
            const h = readHistory();
            h.push({ type, seconds: s, at: Date.now() });
            saveHistory(h);
        }
        function etaFromHistory(type, percent) {
            const p = Math.max(0, Math.min(100, Number(percent || 0)));
            const list = readHistory().filter(x => x?.type === type || x?.type === 'GENERIC');
            if (!list.length) return null;
            const avg = list.reduce((a, b) => a + (Number(b.seconds) || 0), 0) / list.length;
            if (avg <= 0) return null;
            if (p <= 0) return Math.round(avg);
            return Math.max(0, Math.round(avg * (1 - p / 100)));
        }

        function fmtDur(s) {
            if (s === null || s === undefined) return '';
            const n = Math.max(0, Math.round(Number(s)));
            const m = Math.floor(n / 60);
            const r = n % 60;
            return m > 0 ? `${m}m ${r}s` : `${r}s`;
        }

        function setMainFormInteractive(active) {
            const card = document.querySelector('.bg-white.shadow-xl.rounded-lg.overflow-hidden.md\\:flex');
            if (!card) return;
            card.classList.toggle('pointer-events-none', !active);
            card.classList.toggle('select-none', !active);
        }

        function showOverlay() {
            overlay?.classList.remove('hidden');
            sticky?.classList.add('hidden');
            setMainFormInteractive(false);
        }
        function hideOverlay() {
            overlay?.classList.add('hidden');
            sticky?.classList.remove('hidden');
            setMainFormInteractive(true);
        }
        function hideSticky() { sticky?.classList.add('hidden'); }
        function closeImportUi() {
            overlay?.classList.add('hidden');
            sticky?.classList.add('hidden');
            setMainFormInteractive(true);
        }
        function clearPendingImportState() {
            sessionStorage.removeItem('pendingImportTable');
            sessionStorage.removeItem('pendingImportRunId');
            sessionStorage.removeItem('pendingImportAt');
        }
        function resetImportUiOnError(message) {
            stopPolling();
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.style.display = 'none';
            setProgress(0, message || 'Import interrompu.', 'Erreur import');
            closeImportUi();
            clearPendingImportState();
            showErrorBanner(message || 'Erreur lors de l\'import.');
        }

        function showErrorBanner(message) {
            let banner = document.getElementById('importErrorBanner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'importErrorBanner';
                banner.className = 'mb-6 rounded-lg bg-red-100 border border-red-400 text-red-800 px-4 py-3 shadow';
                banner.setAttribute('role', 'alert');
                // Insert after the success banner or at the top of the content
                const successBanner = document.getElementById('importSuccessBanner');
                if (successBanner && successBanner.parentNode) {
                    successBanner.parentNode.insertBefore(banner, successBanner.nextSibling);
                } else {
                    const content = document.querySelector('h1.text-3xl');
                    if (content && content.parentNode) {
                        content.parentNode.insertBefore(banner, content);
                    }
                }
            }
            const safeMessage = String(message || '')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>');
            banner.innerHTML =
                '<div class="flex items-start justify-between gap-3">' +
                    '<div class="min-w-0">' +
                        '<p class="font-bold">Import bloque</p>' +
                        '<p class="mt-1">' + safeMessage + '</p>' +
                    '</div>' +
                    '<button id="closeImportErrorBannerBtn" type="button" class="shrink-0 rounded bg-red-200 hover:bg-red-300 px-2 py-1 text-sm font-semibold" aria-label="Fermer le message">Fermer</button>' +
                '</div>';
            const closeBtn = document.getElementById('closeImportErrorBannerBtn');
            if (closeBtn) {
                closeBtn.onclick = function () {
                    banner.classList.add('hidden');
                };
            }
            banner.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function setProgress(percent, text, stickyText) {
            const p = Math.max(0, Math.min(100, Number(percent || 0)));
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const estimateDiv = document.getElementById('timeEstimate');
            const stickyBar = document.getElementById('stickyBar');
            const stickyTextEl = document.getElementById('stickyText');
            if (progressBar) progressBar.style.width = p + '%';
            if (progressText) progressText.innerText = Math.round(p) + '%';
            if (estimateDiv && text) estimateDiv.innerText = text;
            if (stickyBar) stickyBar.style.width = p + '%';
            if (stickyTextEl && stickyText) stickyTextEl.innerText = stickyText;
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                document.getElementById('filename').innerText = input.files[0].name;
                selectedFileSize = input.files[0].size;
            }
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function startPolling(tableName, runId) {
            if ((!tableName && !runId) || !routeStatus) return;
            stopPolling();
            trackingStartAt = Date.now();
            currentUploadTable = tableName;
            currentUploadRunId = runId || '';
            sessionStorage.setItem('pendingImportTable', tableName);
            if (currentUploadRunId) sessionStorage.setItem('pendingImportRunId', currentUploadRunId);
            sessionStorage.setItem('pendingImportAt', String(Date.now()));
            showOverlay();
            setProgress(1, 'Traitement côté serveur…', 'Import en cours…');

            const statusUrl = currentUploadRunId
                ? (routeStatus + '?run_id=' + encodeURIComponent(currentUploadRunId))
                : (routeStatus + '?table=' + encodeURIComponent(tableName));
            const runType = importTypeFromTable(tableName);
            const spinner = document.getElementById('loadingSpinner');

            const tick = () => {
                fetch(statusUrl, { headers: { Accept: 'application/json' } })
                    .then(r => {
                        if (r.status === 401) {
                            window.location.href = routeLogin;
                            return null;
                        }
                        return r.ok ? r.json() : null;
                    })
                    .then(data => {
                        if (!data) return;
                        const status = data.status || 'running';
                        const count = Number(data.count || 0);
                        const total = Number(data.total || 0);
                        const backendPercent = Number(data.percent || 0);
                        let percent = backendPercent;
                        if ((!percent || percent <= 0) && total > 0) {
                            percent = Math.round((count / total) * 100);
                        }
                        // Single bar over whole process: upload ~20%, backend ~80%.
                        const blendedPercent = Math.max(20, Math.min(99, Math.round(20 + (percent * 0.8))));
                        const eta = etaFromHistory(runType, percent);
                        const etaTxt = eta === null ? '' : ` • ETA historique ~ ${fmtDur(eta)}`;
                        const lineTxt = total > 0 ? `Traitement: ${count} / ${total}${etaTxt}` : `Traitement en cours…${etaTxt}`;
                        setProgress(blendedPercent, lineTxt, `Import… ${Math.round(blendedPercent)}%`);

                        if (status === 'failed' || data.error) {
                            resetImportUiOnError(data.error || 'Import echoue.');
                            return;
                        }
                        if (status === 'done') {
                            stopPolling();
                            if (spinner) spinner.style.display = 'none';
                            const elapsed = Math.round((Date.now() - (trackingStartAt || Date.now())) / 1000);
                            pushHistory(runType, elapsed);
                            setProgress(100, 'Importation terminée avec succès.', 'Import terminé');
                            const banner = document.getElementById('importSuccessBanner');
                            if (banner) {
                                banner.textContent = 'Importation terminée avec succès.';
                                banner.classList.remove('hidden');
                            }
                            sessionStorage.removeItem('pendingImportTable');
                            sessionStorage.removeItem('pendingImportRunId');
                            sessionStorage.removeItem('pendingImportAt');
                            hideOverlay();
                            hideSticky();
                            if (routeConsultation) window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    })
                    .catch(() => {});
            };

            tick();
            pollTimer = setInterval(tick, 2500);
        }

        if (importForm) {
            importForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const spinner = document.getElementById('loadingSpinner');
                if (spinner) spinner.style.display = '';
                showOverlay();
                setProgress(0, 'Upload en cours…', 'Upload…');

                const fd = new FormData(importForm);
                const p = String(fd.get('programme') || '').toUpperCase();
                const r = String(fd.get('reseau') || '').toUpperCase();
                const y = String(fd.get('annee') || '').trim();
                currentUploadTable = (p && r && y) ? `RES_${p}_${r}_${y}` : '';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', importForm.action, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.onprogress = function (ev) {
                    if (!ev.lengthComputable) return;
                    const pc = Math.round((ev.loaded / ev.total) * 100);
                    const blended = Math.round(pc * 0.2);
                    setProgress(blended, `Upload: ${pc}%`, `Upload… ${pc}%`);
                };
                xhr.onload = function () {
                    if (xhr.status === 401) {
                        window.location.href = routeLogin;
                        return;
                    }
                    if (xhr.status >= 200 && xhr.status < 400) {
                        let runId = '';
                        try {
                            const ct = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
                            if (ct.includes('application/json')) {
                                const payload = JSON.parse(xhr.responseText || '{}');
                                if (payload.ok === false && payload.message) {
                                    // Server returned a success HTTP status but logical error (shouldn't happen normally)
                                    resetImportUiOnError(payload.message);
                                    return;
                                }
                                runId = String(payload.run_id || '');
                            }
                        } catch (e) {}
                        setProgress(20, 'Upload terminé. Lancement du traitement...', 'Traitement...');
                        startPolling(currentUploadTable || importTableFromSession, runId);
                        return;
                    }

                    // Handle 4xx/5xx errors (e.g. 422 validation failed, 500 server error)
                    let errorMsg = 'Erreur lors de l\'import.';
                    try {
                        const ct = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
                        if (ct.includes('application/json')) {
                            const errPayload = JSON.parse(xhr.responseText || '{}');
                            if (errPayload.message) {
                                errorMsg = errPayload.message;
                            } else if (errPayload.errors) {
                                // Laravel validation errors format
                                const allErrors = Object.values(errPayload.errors).flat();
                                if (allErrors.length) errorMsg = allErrors.join(' ');
                            }
                        }
                    } catch (e) {}
                    resetImportUiOnError(errorMsg);
                };
                xhr.onerror = function () {
                    resetImportUiOnError('Erreur reseau pendant l upload.');
                };
                xhr.send(fd);
            });
        }

        const minBtn = document.getElementById('minimizeOverlayBtn');
        const closeBtn = document.getElementById('closeOverlayBtn');
        const openStickyBtn = document.getElementById('stickyOpenBtn');
        const dismissStickyBtn = document.getElementById('stickyDismissBtn');
        if (minBtn) minBtn.onclick = hideOverlay;
        if (closeBtn) closeBtn.onclick = hideOverlay;
        if (openStickyBtn) openStickyBtn.onclick = showOverlay;
        if (dismissStickyBtn) dismissStickyBtn.onclick = hideSticky;

        // resume tracking after page reload
        const pending = importTableFromSession || sessionStorage.getItem('pendingImportTable') || '';
        const pendingRunId = sessionStorage.getItem('pendingImportRunId') || '';
        if (pending || pendingRunId) {
            startPolling(pending, pendingRunId);
        }
    </script>
@endsection
