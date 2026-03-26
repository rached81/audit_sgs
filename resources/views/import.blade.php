@extends('layouts.app')

@section('content')
    <!-- Loading Overlay -->
        <div id="loadingOverlay" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md mx-4 w-full relative">
            <div class="absolute top-3 right-3 flex items-center gap-2">
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

            <!-- Steps -->
            <div class="flex items-center justify-between gap-2 text-[11px] font-semibold text-gray-500 mb-4">
                <div id="stepStarting" class="flex-1 rounded-full px-2 py-1 bg-indigo-50 text-indigo-700">Upload</div>
                <div id="stepCleaning" class="flex-1 rounded-full px-2 py-1 bg-gray-100">Nettoyage</div>
                <div id="stepInserting" class="flex-1 rounded-full px-2 py-1 bg-gray-100">Insertion</div>
            </div>

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
         data-events-url="{{ route('import.events', [], false) }}"
         data-status-url="{{ route('import.status', [], false) }}"
         data-consultation-url="{{ route('consultation.index', [], false) }}"
         class="hidden"></div>

    <!-- Sticky mini progress bar (shown when overlay is reduced) -->
    <div id="importSticky"
         class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-[min(46rem,calc(100vw-2rem))] bg-white border border-gray-200 shadow-lg rounded-xl px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm font-bold text-gray-800 truncate">Import en cours</div>
                <div id="stickyText" class="text-xs text-gray-600 truncate">Initialisation…</div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button id="stickyOpenBtn" type="button" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                    Ouvrir
                </button>
                <button id="stickyDismissBtn" type="button" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold">
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
                    <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 hover:bg-gray-50 transition-colors text-center cursor-pointer" onclick="document.getElementById('file').click()">
                        <input type="file" name="file" id="file" class="hidden" accept=".xlsx,.xls,.cvs" onchange="handleFileSelect(this)">
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
        let selectedFileSize = 0;

        function setSteps(active) {
            const starting = document.getElementById('stepStarting');
            const cleaning = document.getElementById('stepCleaning');
            const inserting = document.getElementById('stepInserting');
            if (!starting || !cleaning || !inserting) return;

            const makeActive = (el) => el.className = "flex-1 rounded-full px-2 py-1 bg-indigo-50 text-indigo-700";
            const makeDone = (el) => el.className = "flex-1 rounded-full px-2 py-1 bg-green-50 text-green-700";
            const makeIdle = (el) => el.className = "flex-1 rounded-full px-2 py-1 bg-gray-100 text-gray-500";

            makeIdle(starting); makeIdle(cleaning); makeIdle(inserting);
            if (active === 'starting') { makeActive(starting); }
            if (active === 'cleaning') { makeDone(starting); makeActive(cleaning); }
            if (active === 'inserting') { makeDone(starting); makeDone(cleaning); makeActive(inserting); }
            if (active === 'done') { makeDone(starting); makeDone(cleaning); makeDone(inserting); }
            if (active === 'failed') { makeIdle(starting); makeIdle(cleaning); makeIdle(inserting); makeActive(inserting); }
        }

        function showSticky() {
            const sticky = document.getElementById('importSticky');
            if (sticky) sticky.classList.remove('hidden');
        }

        function hideSticky() {
            const sticky = document.getElementById('importSticky');
            if (sticky) sticky.classList.add('hidden');
        }

        function showOverlay() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('hidden');
            hideSticky();
        }

        function hideOverlay() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('hidden');
            showSticky();
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                document.getElementById('filename').innerText = input.files[0].name;
                selectedFileSize = input.files[0].size; // in bytes
            }
        }

        document.getElementById('importForm').onsubmit = function() {
            // Show Loading Overlay
            showOverlay();
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressText').innerText = '0%';
            document.getElementById('timeEstimate').innerText = "Upload & analyse du fichier...";
            setSteps('starting');
        };

        const cfg = document.getElementById('importProgressConfig');
        const importRunId = cfg?.dataset?.runId || '';
        const importTable = cfg?.dataset?.table || '';
        const routeEvents = cfg?.dataset?.eventsUrl || '';
        const routeStatus = cfg?.dataset?.statusUrl || '';
        const routeConsultation = cfg?.dataset?.consultationUrl || '';

        // Check if we need to follow progress after redirect
        if (importRunId && routeEvents) {
            const sseUrl = routeEvents + "?runId=" + encodeURIComponent(importRunId);
            const estimateDiv = document.getElementById('timeEstimate');
            const overlay = document.getElementById('loadingOverlay');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const spinner = document.getElementById('loadingSpinner');

            showOverlay();
            setSteps('starting');

            let isFinished = false;
            const source = new EventSource(sseUrl);

            source.addEventListener('progress', (event) => {
                if (isFinished) return;

                let data = {};
                try {
                    data = JSON.parse(event.data);
                } catch (e) {
                    console.error(e);
                    return;
                }

                const overall = data.overall_percent ?? 0;
                const stage = data.stage ?? 'running';
                const stagePc = data.stage_percent ?? 0;
                const processed = data.processed ?? 0;
                const total = data.total ?? 0;
                const status = data.status ?? 'running';
                const error = data.error ?? null;
                const eta = data.eta_seconds ?? null;

                progressBar.style.width = overall + '%';
                progressText.innerText = overall + '%';
                const stickyBar = document.getElementById('stickyBar');
                const stickyText = document.getElementById('stickyText');
                if (stickyBar) stickyBar.style.width = overall + '%';
                if (stickyText) stickyText.innerText = (stage === 'starting' ? 'Upload…' : 'Import…') + " (" + overall + "%)";

                let stageLabel = stage;
                if (stage === 'cleaning') stageLabel = 'Nettoyage';
                if (stage === 'inserting') stageLabel = 'Insertion';
                if (stage === 'starting') stageLabel = 'Upload';
                if (stage === 'starting') setSteps('starting');
                if (stage === 'cleaning') setSteps('cleaning');
                if (stage === 'inserting') setSteps('inserting');

                const etaTxt = eta === null ? '' : (" • ETA ~ " + eta + "s");
                if (stage === 'inserting') {
                    estimateDiv.innerText = stageLabel + " (" + stagePc + "%)" + " • Lignes: " + processed + " / " + total + etaTxt;
                } else if (stage === 'cleaning') {
                    estimateDiv.innerText = stageLabel + " (" + stagePc + "%)" + " • Lignes prêtes: " + processed + etaTxt;
                } else {
                    estimateDiv.innerText = stageLabel + "..." + etaTxt;
                }

                if (error || status === 'failed') {
                    isFinished = true;
                    spinner.style.display = 'none';
                    setSteps('failed');
                    estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import echoue : " + (error ?? "Erreur inconnue") + "</span>";
                    source.close();
                    return;
                }

                if (status === 'done') {
                    isFinished = true;
                    estimateDiv.innerHTML = "<span class='text-green-600 font-bold text-lg'>Importation terminee avec succes !</span>";
                    document.getElementById('infoText').innerText = "";
                    spinner.style.display = 'none';
                    setSteps('done');
                    hideSticky();

                    if (!document.getElementById('finishBtn')) {
                        const btn = document.createElement('a');
                        btn.id = 'finishBtn';
                        btn.href = routeConsultation;
                        btn.innerText = "Consulter les donnees";
                        btn.className = "mt-6 inline-block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded shadow transition-colors";
                        document.querySelector('#loadingOverlay > div').appendChild(btn);
                    }

                    const closeBtn = document.getElementById('closeOverlayBtn');
                    if (closeBtn) closeBtn.remove();

                    source.close();
                }
            });

            source.onerror = (err) => {
                console.error(err);
                estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Connexion au suivi SSE interrompue. Vous pouvez reduire l'overlay et reessayer (recharger la page).</span>";
                showSticky();
            };
        } else if (importTable && routeStatus) {
            // Fallback legacy polling (table-based)
            const statusUrl = routeStatus + "?table=" + encodeURIComponent(importTable);
            const estimateDiv = document.getElementById('timeEstimate');
            const overlay = document.getElementById('loadingOverlay');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const spinner = document.getElementById('loadingSpinner');

            showOverlay();
            setSteps('starting');

            let isFinished = false;
            let consecutiveFailures = 0;
            const maxFailuresBeforeWarning = 3;

            let pollInterval = setInterval(function() {
                if (isFinished) return;

                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(response => {
                        if (response.status === 401) {
                            window.location.href = '/login';
                            return;
                        }
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        // If backend returns HTML (redirect/login/error page), JSON parsing will fail.
                        return response.text().then((txt) => {
                            try {
                                return JSON.parse(txt);
                            } catch (e) {
                                const err = new Error('Non-JSON response from status endpoint');
                                err.raw = txt?.slice?.(0, 2000);
                                throw err;
                            }
                        });
                    })
                    .then(data => {
                        consecutiveFailures = 0;
                        // Prefer backend stage-aware progress when available.
                        let pc = Number.isFinite(data.overall_percent) ? data.overall_percent : (data.percent || 0);
                        let count = data.count || 0;
                        let total = data.total || '?';
                        let status = data.status || 'running';
                        let error = data.error || null;
                        let stage = data.stage || '';
                        let eta = data.eta_seconds ?? null;
                        let stagePc = data.stage_percent ?? null;

                        const etaTxt = eta === null ? '' : (" • ETA ~ " + eta + "s");
                        if (stage === 'cleaning') {
                            setSteps('cleaning');
                            const sp = stagePc === null ? '' : (" (" + stagePc + "%)");
                            estimateDiv.innerText = "Nettoyage" + sp + " • Lignes prêtes: " + count + etaTxt;
                        } else if (stage === 'inserting' || stage === 'done') {
                            setSteps('inserting');
                            estimateDiv.innerText = "Insertion • Lignes: " + count + " / " + total + etaTxt;
                        } else {
                            setSteps('starting');
                            estimateDiv.innerText = "Initialisation..." + etaTxt;
                        }
                        const stickyBar = document.getElementById('stickyBar');
                        const stickyText = document.getElementById('stickyText');
                        if (stickyBar) stickyBar.style.width = pc + '%';
                        if (stickyText) {
                            if (stage === 'cleaning') {
                                const sp = stagePc === null ? '' : (" (" + stagePc + "%)");
                                stickyText.innerText = "Nettoyage" + sp + " • Lignes prêtes: " + count;
                            } else if (stage === 'inserting' || stage === 'done') {
                                stickyText.innerText = "Insertion • " + count + " / " + total + " (" + pc + "%)";
                            } else {
                                stickyText.innerText = "Initialisation… (" + pc + "%)";
                            }
                        }

                        progressBar.style.width = pc + '%';
                        progressText.innerText = pc + '%';

                        if (error || status === 'failed') {
                             isFinished = true;
                             spinner.style.display = 'none';
                             setSteps('failed');
                             estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import echoue : " + (error ?? "Erreur inconnue") + "</span>";
                             clearInterval(pollInterval);
                             return;
                        }

                        if (status === 'done') {
                             isFinished = true;
                             estimateDiv.innerHTML = "<span class='text-green-600 font-bold text-lg'>Importation terminee avec succes !</span>";
                             document.getElementById('infoText').innerText = "";
                             spinner.style.display = 'none';
                             setSteps('done');
                             hideSticky();

                             if (!document.getElementById('finishBtn')) {
                                 const btn = document.createElement('a');
                                 btn.id = 'finishBtn';
                                 btn.href = routeConsultation;
                                 btn.innerText = "Consulter les donnees";
                                 btn.className = "mt-6 inline-block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded shadow transition-colors";
                                 document.querySelector('#loadingOverlay > div').appendChild(btn);
                             }

                             const closeBtn = document.getElementById('closeOverlayBtn');
                             if(closeBtn) closeBtn.remove();

                             clearInterval(pollInterval);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        consecutiveFailures++;
                        // In synchronous mode (especially with single-threaded dev server),
                        // /import/status can temporarily fail while the import is running.
                        // Keep retrying instead of showing a hard error immediately.
                        if (consecutiveFailures >= maxFailuresBeforeWarning) {
                            estimateDiv.innerHTML =
                                "<span class='text-red-600 font-bold'>Suivi temporairement indisponible (" + consecutiveFailures + ").</span><br>" +
                                "<span class='text-gray-600 text-sm'>On reessaie automatiquement... Vous pouvez reduire l'overlay.</span>";
                        }
                        showSticky();
                    });
            }, 2000);
        }

        // Background/details UX wiring
            const minimizeOverlayBtn = document.getElementById('minimizeOverlayBtn');
            const closeOverlayBtn = document.getElementById('closeOverlayBtn');
        const stickyOpenBtn = document.getElementById('stickyOpenBtn');
        const stickyDismissBtn = document.getElementById('stickyDismissBtn');

            if (minimizeOverlayBtn) minimizeOverlayBtn.onclick = hideOverlay;
            if (closeOverlayBtn) closeOverlayBtn.onclick = hideOverlay;
        if (stickyOpenBtn) stickyOpenBtn.onclick = showOverlay;
        if (stickyDismissBtn) stickyDismissBtn.onclick = hideSticky;
    </script>
@endsection
