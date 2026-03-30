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
            <div class="mb-4">
                <button id="cancelImportBtn" type="button"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow transition-colors">
                    Annuler l'import
                </button>
            </div>

            <!-- Steps (vertical) -->
            <div class="mb-4 text-left">
                <div class="text-xs font-bold text-gray-500 mb-2">Étapes</div>
                <div class="space-y-2">
                    <div id="stepUploadRow" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2">
                        <div class="min-w-0 flex items-center gap-2">
                            <span id="stepUploadIcon" class="shrink-0 h-5 w-5 rounded-full bg-indigo-100 text-indigo-700 grid place-items-center font-extrabold">•</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800">Upload</div>
                                <div id="stepUploadMeta" class="text-xs text-gray-500 truncate">En attente…</div>
                            </div>
                        </div>
                        <div id="stepUploadRight" class="shrink-0 text-xs font-bold text-gray-600">0%</div>
                    </div>

                    <div id="stepMapRow" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2">
                        <div class="min-w-0 flex items-center gap-2">
                            <span id="stepMapIcon" class="shrink-0 h-5 w-5 rounded-full bg-gray-100 text-gray-500 grid place-items-center font-extrabold">•</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800">Mappage entête</div>
                                <div id="stepMapMeta" class="text-xs text-gray-500 truncate">Analyse des colonnes…</div>
                            </div>
                        </div>
                        <div id="stepMapRight" class="shrink-0 text-xs font-bold text-gray-600">—</div>
                    </div>

                    <div id="stepCleaningRow" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2">
                        <div class="min-w-0 flex items-center gap-2">
                            <span id="stepCleaningIcon" class="shrink-0 h-5 w-5 rounded-full bg-gray-100 text-gray-500 grid place-items-center font-extrabold">•</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800">Nettoyage</div>
                                <div id="stepCleaningMeta" class="text-xs text-gray-500 truncate">En attente…</div>
                            </div>
                        </div>
                        <div id="stepCleaningRight" class="shrink-0 text-xs font-bold text-gray-600">0%</div>
                    </div>

                    <div id="stepInsertingRow" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2">
                        <div class="min-w-0 flex items-center gap-2">
                            <span id="stepInsertingIcon" class="shrink-0 h-5 w-5 rounded-full bg-gray-100 text-gray-500 grid place-items-center font-extrabold">•</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800">Insertion</div>
                                <div id="stepInsertingMeta" class="text-xs text-gray-500 truncate">En attente…</div>
                            </div>
                        </div>
                        <div id="stepInsertingRight" class="shrink-0 text-xs font-bold text-gray-600">0%</div>
                    </div>
                </div>
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
         data-cancel-url="{{ route('import.cancel', [], false) }}"
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

        function setStepRowState(rowId, iconId, state) {
            const row = document.getElementById(rowId);
            const icon = document.getElementById(iconId);
            if (!row || !icon) return;

            const baseRow = "flex items-center justify-between gap-3 rounded-lg border px-3 py-2";
            const baseIcon = "shrink-0 h-5 w-5 rounded-full grid place-items-center font-extrabold";

            if (state === 'active') {
                row.className = baseRow + " border-indigo-200 bg-indigo-50";
                icon.className = baseIcon + " bg-indigo-100 text-indigo-700";
                icon.innerText = "…";
                return;
            }
            if (state === 'done') {
                row.className = baseRow + " border-green-200 bg-green-50";
                icon.className = baseIcon + " bg-green-100 text-green-700";
                icon.innerText = "✓";
                return;
            }
            if (state === 'failed') {
                row.className = baseRow + " border-red-200 bg-red-50";
                icon.className = baseIcon + " bg-red-100 text-red-700";
                icon.innerText = "!";
                return;
            }
            // idle
            row.className = baseRow + " border-gray-200 bg-white";
            icon.className = baseIcon + " bg-gray-100 text-gray-500";
            icon.innerText = "•";
        }

        function setSteps(active) {
            // active: starting|mapping|cleaning|inserting|done|failed
            if (active === 'starting') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'active');
                setStepRowState('stepMapRow', 'stepMapIcon', 'idle');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'idle');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'idle');
                return;
            }
            if (active === 'mapping') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'done');
                setStepRowState('stepMapRow', 'stepMapIcon', 'active');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'idle');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'idle');
                return;
            }
            if (active === 'cleaning') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'done');
                setStepRowState('stepMapRow', 'stepMapIcon', 'done');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'active');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'idle');
                return;
            }
            if (active === 'inserting') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'done');
                setStepRowState('stepMapRow', 'stepMapIcon', 'done');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'done');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'active');
                return;
            }
            if (active === 'done') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'done');
                setStepRowState('stepMapRow', 'stepMapIcon', 'done');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'done');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'done');
                return;
            }
            if (active === 'failed') {
                setStepRowState('stepUploadRow', 'stepUploadIcon', 'done');
                setStepRowState('stepMapRow', 'stepMapIcon', 'done');
                setStepRowState('stepCleaningRow', 'stepCleaningIcon', 'done');
                setStepRowState('stepInsertingRow', 'stepInsertingIcon', 'failed');
                return;
            }
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

        function formatBytes(bytes) {
            const b = Number(bytes || 0);
            if (!Number.isFinite(b) || b <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const i = Math.min(units.length - 1, Math.floor(Math.log(b) / Math.log(1024)));
            const v = b / Math.pow(1024, i);
            return (v >= 10 || i === 0 ? v.toFixed(0) : v.toFixed(1)) + ' ' + units[i];
        }

        function formatDuration(seconds) {
            if (seconds === null || seconds === undefined) return '';
            const s = Math.max(0, Math.round(seconds));
            const m = Math.floor(s / 60);
            const r = s % 60;
            if (m <= 0) return s + 's';
            return m + 'm ' + r + 's';
        }

        let currentUploadTable = '';

        // AJAX upload with real progress (XHR upload.onprogress).
        const importForm = document.getElementById('importForm');
        if (importForm) {
            importForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const overlay = document.getElementById('loadingOverlay');
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const estimateDiv = document.getElementById('timeEstimate');
                const infoText = document.getElementById('infoText');
                const spinner = document.getElementById('loadingSpinner');

                showOverlay();
                setSteps('starting');
                hideSticky();

                if (spinner) spinner.style.display = '';
                if (progressBar) progressBar.style.width = '0%';
                if (progressText) progressText.innerText = '0%';
                if (infoText) infoText.innerText = 'Veuillez patienter, ne fermez pas la page.';
                if (estimateDiv) estimateDiv.innerText = 'Upload en cours…';
                const upMeta = document.getElementById('stepUploadMeta');
                const upRight = document.getElementById('stepUploadRight');
                const mapMeta = document.getElementById('stepMapMeta');
                const mapRight = document.getElementById('stepMapRight');
                const clMeta = document.getElementById('stepCleaningMeta');
                const clRight = document.getElementById('stepCleaningRight');
                const insMeta = document.getElementById('stepInsertingMeta');
                const insRight = document.getElementById('stepInsertingRight');
                if (upMeta) upMeta.innerText = 'En attente…';
                if (upRight) upRight.innerText = '0%';
                if (mapMeta) mapMeta.innerText = 'Analyse des colonnes…';
                if (mapRight) mapRight.innerText = '—';
                if (clMeta) clMeta.innerText = 'En attente…';
                if (clRight) clRight.innerText = '0%';
                if (insMeta) insMeta.innerText = 'En attente…';
                if (insRight) insRight.innerText = '0%';

                const fd = new FormData(importForm);
                const xhr = new XMLHttpRequest();
                // Fallback context in case flash session is not available after XHR redirects.
                const p = String(fd.get('programme') || '').toUpperCase();
                const r = String(fd.get('reseau') || '').toUpperCase();
                const y = String(fd.get('annee') || '').trim();
                if (p && r && y) {
                    currentUploadTable = ('RES_' + p + '_' + r + '_' + y);
                    sessionStorage.setItem('pendingImportTable', currentUploadTable);
                }
                sessionStorage.setItem('pendingImportAt', String(Date.now()));

                const startAt = Date.now();
                let lastAt = startAt;
                let lastLoaded = 0;

                xhr.open('POST', importForm.action, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.onprogress = function (ev) {
                    if (!ev.lengthComputable) return;
                    const pc = Math.max(0, Math.min(100, Math.round((ev.loaded / ev.total) * 100)));

                    if (progressBar) progressBar.style.width = pc + '%';
                    if (progressText) progressText.innerText = pc + '%';

                    const now = Date.now();
                    const dt = Math.max(1, now - lastAt) / 1000;
                    const dBytes = Math.max(0, ev.loaded - lastLoaded);
                    const speed = dBytes / dt; // bytes/s
                    const remaining = Math.max(0, ev.total - ev.loaded);
                    const eta = speed > 0 ? (remaining / speed) : null;

                    lastAt = now;
                    lastLoaded = ev.loaded;

                    const etaTxt = eta === null ? '' : (' • ETA ~ ' + formatDuration(eta));
                    const speedTxt = speed > 0 ? (' • ' + formatBytes(speed) + '/s') : '';
                    if (estimateDiv) {
                        estimateDiv.innerText =
                            'Upload (' + pc + '%) • ' + formatBytes(ev.loaded) + ' / ' + formatBytes(ev.total) + speedTxt + etaTxt;
                    }
                    if (upMeta) upMeta.innerText = 'Upload (' + pc + '%)' + (eta === null ? '' : (' • ETA ~ ' + formatDuration(eta)));
                    if (upRight) upRight.innerText = pc + '%';

                    // Upload done, now backend is still validating headers before response.
                    if (pc >= 100) {
                        setSteps('mapping');
                        if (estimateDiv) estimateDiv.innerText = "Upload terminé • Analyse entête côté serveur…";
                        if (mapMeta) mapMeta.innerText = "Analyse des colonnes en cours…";
                        if (mapRight) mapRight.innerText = "…";
                    }

                    const stickyBar = document.getElementById('stickyBar');
                    const stickyText = document.getElementById('stickyText');
                    if (stickyBar) stickyBar.style.width = pc + '%';
                    if (stickyText) stickyText.innerText = 'Upload… ' + pc + '%';
                };

                xhr.onload = function () {
                    // After upload finishes, backend may have redirected (mapping page or back to import).
                    // We navigate to the final URL so normal polling logic resumes.
                    if (xhr.status === 401) {
                        window.location.href = '/login';
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 400) {
                        if (estimateDiv) estimateDiv.innerText = 'Upload terminé. Préparation…';
                        if (upMeta) upMeta.innerText = 'Terminé';
                        if (upRight) upRight.innerText = '100%';
                        setSteps('mapping');

                        const ct = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
                        const isJson = ct.includes('application/json');
                        if (isJson) {
                            let payload = null;
                            try {
                                payload = JSON.parse(xhr.responseText || '{}');
                            } catch (e) {
                                payload = null;
                            }

                            if (!payload || payload.ok === false) {
                                if (spinner) spinner.style.display = 'none';
                                setSteps('failed');
                                const msg = payload?.message || 'Réponse invalide du serveur pendant le démarrage de l\'import.';
                                if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>" + msg + "</span>";
                                showSticky();
                                return;
                            }

                            const trackedTable = String(payload.table_name || currentUploadTable || sessionStorage.getItem('pendingImportTable') || '');
                            if (trackedTable && typeof startPollingTracking === 'function') {
                                sessionStorage.setItem('pendingImportTable', trackedTable);
                                sessionStorage.setItem('pendingImportAt', String(Date.now()));
                                startPollingTracking(trackedTable);
                                return;
                            }
                        }

                        const html = typeof xhr.responseText === 'string' ? xhr.responseText : '';
                        const looksLikeHtml = ct.includes('text/html') && html.trim().startsWith('<');
                        const looksLikeMapping = looksLikeHtml && (
                            html.includes('name="mapping[') ||
                            html.includes("name='mapping[") ||
                            html.toLowerCase().includes('import_mapping')
                        );

                        // If manual mapping is required, render that page.
                        if (looksLikeMapping) {
                            document.open();
                            document.write(html);
                            document.close();
                            return;
                        }

                        // Standard flow: keep this page and start polling immediately (avoid modal closing flicker).
                        const tableName = currentUploadTable || sessionStorage.getItem('pendingImportTable') || '';
                        if (tableName && typeof startPollingTracking === 'function') {
                            // Keep state durable in case browser/page does an unexpected refresh.
                            sessionStorage.setItem('pendingImportTable', tableName);
                            sessionStorage.setItem('pendingImportAt', String(Date.now()));
                            startPollingTracking(tableName);
                            return;
                        }

                        // Keep modal visible with explicit message if table could not be resolved.
                        if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Impossible de démarrer le suivi (table introuvable).</span>";
                        setSteps('failed');
                        return;
                    }

                    // Error
                    if (spinner) spinner.style.display = 'none';
                    setSteps('failed');
                    const msg = 'Erreur upload (HTTP ' + xhr.status + ').';
                    if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>" + msg + "</span>";
                    showSticky();
                };

                xhr.onerror = function () {
                    if (spinner) spinner.style.display = 'none';
                    setSteps('failed');
                    if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Erreur réseau pendant l'upload.</span>";
                    showSticky();
                };

                xhr.send(fd);
            });
        }

        const cfg = document.getElementById('importProgressConfig');
        const importRunIdFromSession = cfg?.dataset?.runId || '';
        const importTableFromSession = cfg?.dataset?.table || '';
        // Robust fallback for XHR upload -> redirect chain where flash may be consumed.
        const importRunId = importRunIdFromSession || (sessionStorage.getItem('pendingImportRunId') || '');
        const importTable = importTableFromSession || (sessionStorage.getItem('pendingImportTable') || '');
        const routeEvents = cfg?.dataset?.eventsUrl || '';
        const routeStatus = cfg?.dataset?.statusUrl || '';
        const routeCancel = cfg?.dataset?.cancelUrl || '';
        const routeConsultation = cfg?.dataset?.consultationUrl || '';

        function clearPendingImport() {
            sessionStorage.removeItem('pendingImportTable');
            sessionStorage.removeItem('pendingImportRunId');
            sessionStorage.removeItem('pendingImportAt');
        }

        function getCsrfToken() {
            const el = document.querySelector('input[name="_token"]');
            return el?.value || '';
        }

        function requestCancel(tableName) {
            if (!routeCancel || !tableName) return Promise.resolve(false);
            const token = getCsrfToken();
            return fetch(routeCancel, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({ table: tableName }),
            })
            .then(r => r.ok ? r.json().catch(() => ({})) : Promise.reject(r))
            .then(() => true)
            .catch(() => false);
        }

        function isPendingImportRecent(maxAgeMs = 10 * 60 * 1000) {
            const startedAt = Number(sessionStorage.getItem('pendingImportAt') || 0);
            if (!Number.isFinite(startedAt) || startedAt <= 0) return false;
            return (Date.now() - startedAt) <= maxAgeMs;
        }

        // Allow starting polling from upload handler without reloading.
        let activePollInterval = null;
        function startPollingTracking(tableName) {
            if (!tableName || !routeStatus) return;
            if (activePollInterval) {
                clearInterval(activePollInterval);
                activePollInterval = null;
            }

            const statusUrl = routeStatus + "?table=" + encodeURIComponent(tableName);
            const estimateDiv = document.getElementById('timeEstimate');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const spinner = document.getElementById('loadingSpinner');

            showOverlay();
            setSteps('mapping');

            let isFinished = false;
            let consecutiveFailures = 0;
            const maxFailuresBeforeWarning = 3;

            activePollInterval = setInterval(function() {
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
                            if (estimateDiv) estimateDiv.innerText = "Nettoyage" + sp + " • Lignes prêtes: " + count + etaTxt;
                            const clMeta = document.getElementById('stepCleaningMeta');
                            const clRight = document.getElementById('stepCleaningRight');
                            if (clMeta) clMeta.innerText = "Lignes prêtes: " + count + etaTxt;
                            if (clRight) clRight.innerText = (stagePc === null ? '—' : (stagePc + '%'));
                        } else if (stage === 'inserting' || stage === 'done') {
                            setSteps('inserting');
                            if (estimateDiv) estimateDiv.innerText = "Insertion • Lignes: " + count + " / " + total + etaTxt;
                            const insMeta = document.getElementById('stepInsertingMeta');
                            const insRight = document.getElementById('stepInsertingRight');
                            if (insMeta) insMeta.innerText = count + " / " + total + etaTxt;
                            if (insRight) insRight.innerText = '—';
                        } else {
                            setSteps('mapping');
                            if (estimateDiv) estimateDiv.innerText = "Mappage entête..." + etaTxt;
                        }

                        const stickyBar = document.getElementById('stickyBar');
                        const stickyText = document.getElementById('stickyText');
                        if (stickyBar) stickyBar.style.width = pc + '%';
                        if (stickyText) stickyText.innerText = "Import… " + count + " / " + total + " (" + pc + "%)";

                        if (progressBar) progressBar.style.width = pc + '%';
                        if (progressText) progressText.innerText = pc + '%';

                        if (error || status === 'failed') {
                             isFinished = true;
                             clearPendingImport();
                             if (spinner) spinner.style.display = 'none';
                             setSteps('failed');
                             if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import echoue : " + (error ?? "Erreur inconnue") + "</span>";
                             clearInterval(activePollInterval);
                             activePollInterval = null;
                             return;
                        }

                        if (status === 'cancelled') {
                             isFinished = true;
                             clearPendingImport();
                             if (spinner) spinner.style.display = 'none';
                             setSteps('failed');
                             if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import annulé.</span>";
                             hideSticky();
                             clearInterval(activePollInterval);
                             activePollInterval = null;
                             return;
                        }

                        if (status === 'done') {
                             isFinished = true;
                             clearPendingImport();
                             if (estimateDiv) estimateDiv.innerHTML = "<span class='text-green-600 font-bold text-lg'>Importation terminee avec succes !</span>";
                             const infoText = document.getElementById('infoText');
                             if (infoText) infoText.innerText = "";
                             if (spinner) spinner.style.display = 'none';
                             setSteps('done');
                             hideSticky();
                             clearInterval(activePollInterval);
                             activePollInterval = null;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        consecutiveFailures++;
                        if (consecutiveFailures >= maxFailuresBeforeWarning && estimateDiv) {
                            estimateDiv.innerHTML =
                                "<span class='text-red-600 font-bold'>Suivi temporairement indisponible (" + consecutiveFailures + ").</span><br>" +
                                "<span class='text-gray-600 text-sm'>On reessaie automatiquement... Vous pouvez reduire l'overlay.</span>";
                        }
                        showSticky();
                    });
            }, 2000);
        }

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
                if (stage === 'starting') setSteps('mapping');
                if (stage === 'cleaning') setSteps('cleaning');
                if (stage === 'inserting') setSteps('inserting');

                const etaTxt = eta === null ? '' : (" • ETA ~ " + eta + "s");
                if (stage === 'inserting') {
                    estimateDiv.innerText = stageLabel + " (" + stagePc + "%)" + " • Lignes: " + processed + " / " + total + etaTxt;
                    const insMeta = document.getElementById('stepInsertingMeta');
                    const insRight = document.getElementById('stepInsertingRight');
                    if (insMeta) insMeta.innerText = processed + " / " + total + etaTxt;
                    if (insRight) insRight.innerText = stagePc + '%';
                } else if (stage === 'cleaning') {
                    estimateDiv.innerText = stageLabel + " (" + stagePc + "%)" + " • Lignes prêtes: " + processed + etaTxt;
                    const clMeta = document.getElementById('stepCleaningMeta');
                    const clRight = document.getElementById('stepCleaningRight');
                    if (clMeta) clMeta.innerText = "Lignes prêtes: " + processed + etaTxt;
                    if (clRight) clRight.innerText = stagePc + '%';
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
                    clearPendingImport();
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
            startPollingTracking(importTable);
        } else if (routeStatus) {
            // If we only have a pending table from sessionStorage, validate status first.
            const pendingTable = sessionStorage.getItem('pendingImportTable') || '';
            if (pendingTable) {
                const checkUrl = routeStatus + "?table=" + encodeURIComponent(pendingTable);
                fetch(checkUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : null)
                    .then(data => {
                        if (!data) return;
                        const st = data.status || 'idle';
                        if (st === 'done') {
                            clearPendingImport();
                            return;
                        }
                        if (st === 'idle') {
                            // Avoid dropping progress too early: cache may not be populated yet.
                            if (isPendingImportRecent()) {
                                startPollingTracking(pendingTable);
                                return;
                            }
                            clearPendingImport();
                            return;
                        }
                        startPollingTracking(pendingTable);
                    })
                    .catch(() => {
                        // Silent fallback: do not force modal if we cannot validate pending state.
                        if (isPendingImportRecent()) {
                            startPollingTracking(pendingTable);
                        }
                    });
            }
        }

        // Background/details UX wiring
            const minimizeOverlayBtn = document.getElementById('minimizeOverlayBtn');
            const closeOverlayBtn = document.getElementById('closeOverlayBtn');
        const cancelImportBtn = document.getElementById('cancelImportBtn');
        const stickyOpenBtn = document.getElementById('stickyOpenBtn');
        const stickyDismissBtn = document.getElementById('stickyDismissBtn');

            if (minimizeOverlayBtn) minimizeOverlayBtn.onclick = hideOverlay;
            if (closeOverlayBtn) closeOverlayBtn.onclick = hideOverlay;
        if (stickyOpenBtn) stickyOpenBtn.onclick = showOverlay;
        if (stickyDismissBtn) stickyDismissBtn.onclick = hideSticky;

        if (cancelImportBtn) {
            cancelImportBtn.onclick = function () {
                const tableName = currentUploadTable || sessionStorage.getItem('pendingImportTable') || '';
                if (!tableName) return;
                cancelImportBtn.disabled = true;
                cancelImportBtn.innerText = 'Annulation...';
                requestCancel(tableName).then((ok) => {
                    if (!ok) {
                        cancelImportBtn.disabled = false;
                        cancelImportBtn.innerText = "Annuler l'import";
                        return;
                    }
                    clearPendingImport();
                    setSteps('failed');
                    const estimateDiv = document.getElementById('timeEstimate');
                    const spinner = document.getElementById('loadingSpinner');
                    if (spinner) spinner.style.display = 'none';
                    if (estimateDiv) estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import annulé.</span>";
                    hideSticky();
                });
            };
        }
    </script>
@endsection
