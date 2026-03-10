@extends('layouts.app')

@section('content')
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md mx-4 w-full">
            <div id="loadingSpinner" class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-16 w-16 mx-auto mb-4 border-indigo-600"></div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Import en cours...</h2>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-4 mb-1 relative overflow-hidden">
                <div id="progressBar" class="bg-indigo-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <div id="progressText" class="text-sm text-indigo-700 font-bold mb-4">0%</div>

            <p class="text-gray-600 mb-4">Veuillez patienter, ne fermez pas la page.</p>
            <div id="timeEstimate" class="text-sm font-semibold text-indigo-600 bg-indigo-50 py-2 px-4 rounded">
                Initialisation...
            </div>
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

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                document.getElementById('filename').innerText = input.files[0].name;
                selectedFileSize = input.files[0].size; // in bytes
            }
        }

        document.getElementById('importForm').onsubmit = function() {
            // Show Loading Overlay
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressText').innerText = '0%';
            document.getElementById('timeEstimate').innerText = "Analyse du fichier...";
        };

        // Check if we need to poll for progress (Flash session variable passed to view)
        @if(session('import_table'))
            const importTable = "{{ session('import_table') }}";
            const statusUrl = "{{ route('import.status') }}?table=" + importTable;
            const estimateDiv = document.getElementById('timeEstimate');
            const overlay = document.getElementById('loadingOverlay');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const spinner = document.getElementById('loadingSpinner');

            // Re-open overlay if it was closed (page reload)
            overlay.classList.remove('hidden');

            let isFinished = false;

            let pollInterval = setInterval(function() {
                if (isFinished) return;

                fetch(statusUrl)
                    .then(response => {
                        if (response.status === 401) {
                            window.location.href = '/login';
                            return;
                        }
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        let pc = data.percent || 0;
                        let count = data.count || 0;
                        let total = data.total || '?';
                        let status = data.status || 'running';
                        let error = data.error || null;

                        estimateDiv.innerText = "Lignes importees : " + count + " / " + total;

                        // Update Bar
                        progressBar.style.width = pc + '%';
                        progressText.innerText = pc + '%';

                        if (error || status === 'failed') {
                             isFinished = true;
                             spinner.style.display = 'none';
                             estimateDiv.innerHTML = "<span class='text-red-600 font-bold'>Import echoue : " + (error ?? "Erreur inconnue") + "</span>";
                             clearInterval(pollInterval);
                             return;
                        }

                        if (status === 'done') {
                             isFinished = true;
                             estimateDiv.innerHTML = "<span class='text-green-600 font-bold text-lg'>Importation terminee avec succes !</span>";
                             spinner.style.display = 'none'; // Hide spinner

                             // Add a finish button
                             if (!document.getElementById('finishBtn')) {
                                 const btn = document.createElement('a');
                                 btn.id = 'finishBtn';
                                 btn.href = "{{ route('consultation.index') }}";
                                 btn.innerText = "Consulter les donnees";
                                 btn.className = "mt-6 inline-block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded shadow transition-colors";
                                 document.querySelector('#loadingOverlay > div').appendChild(btn);
                             }

                             // Remove Close button if exists
                             const closeBtn = document.getElementById('closeOverlayBtn');
                             if(closeBtn) closeBtn.remove();

                             clearInterval(pollInterval);
                        }
                    })
                    .catch(err => console.error(err));
            }, 1000); // Poll every 1 second

            // Add Close Button (only while running)
            const container = document.querySelector('#loadingOverlay > div');
            if (!document.getElementById('closeOverlayBtn')) {
                const btn = document.createElement('button');
                btn.id = 'closeOverlayBtn';
                btn.innerText = "Réduire en arrière-plan";
                btn.className = "mt-4 text-sm text-gray-500 hover:text-gray-700 underline";
                btn.onclick = function() {
                    overlay.classList.add('hidden');
                };
                container.appendChild(btn);
            }
        @endif
    </script>
@endsection
