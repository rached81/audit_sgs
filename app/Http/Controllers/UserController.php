<?php

namespace App\Http\Controllers;

use App\Services\ImportOperationLogger;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function __construct(
        private ImportOperationLogger $operationLogger
    ) {
    }

    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'unite' => 'nullable|string|max:255',
            'matricule' => ['required', 'string', 'max:255', Rule::unique('users', 'matricule')->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'profile' => 'required|string|in:user,admin,superadmin',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        // Populate name from nom/prenom if not explicitly handled
        $validated['name'] = $validated['nom'] . ' ' . $validated['prenom'];

        $created = User::create(array_merge($validated, [
            'must_change_password' => true,
        ]));

        $actor = $request->user();
        $actorName = trim((string) (($actor?->prenom ?? '') . ' ' . ($actor?->nom ?? '')));
        $this->operationLogger->log([
            'run_id' => (string) str()->uuid(),
            'table_name' => 'users',
            'operation' => 'create_user',
            'status' => 'success',
            'user_id' => $actor?->id,
            'user_matricule' => $actor?->matricule,
            'user_name' => $actorName !== '' ? $actorName : null,
            'ip_address' => $request->ip(),
            'message' => "Ajout utilisateur {$created->matricule}.",
            'context' => [
                'created_user_id' => $created->id,
                'created_matricule' => $created->matricule,
                'created_profile' => $created->profile,
                'route' => $request->route()?->getName(),
            ],
        ]);

        return redirect()->route('users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'unite' => 'nullable|string|max:255',
            'matricule' => ['required', 'string', 'max:255', Rule::unique('users', 'matricule')->whereNull('deleted_at')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->whereNull('deleted_at')->ignore($user->id)],
            'profile' => 'required|string|in:user,admin,superadmin',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        
        $validated['name'] = $validated['nom'] . ' ' . $validated['prenom'];

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour avec succès.');
    }

    public function destroy(User $user)
    {
        $request = request();
        $actor = $request->user();
        $actorName = trim((string) (($actor?->prenom ?? '') . ' ' . ($actor?->nom ?? '')));
        $hasOperations = $this->userHasOperations($user);

        if ($hasOperations) {
            $user->delete(); // logical delete (SoftDelete)
            $mode = 'logical';
        } else {
            $user->forceDelete(); // hard delete if no operation history
            $mode = 'physical';
        }

        $this->operationLogger->log([
            'run_id' => (string) str()->uuid(),
            'table_name' => 'users',
            'operation' => 'delete_user',
            'status' => 'success',
            'user_id' => $actor?->id,
            'user_matricule' => $actor?->matricule,
            'user_name' => $actorName !== '' ? $actorName : null,
            'ip_address' => $request->ip(),
            'message' => "Suppression utilisateur {$user->matricule} ({$mode}).",
            'context' => [
                'deleted_user_id' => $user->id,
                'deleted_matricule' => $user->matricule,
                'mode' => $mode,
                'route' => $request->route()?->getName(),
            ],
        ]);

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé avec succès.');
    }

    private function userHasOperations(User $user): bool
    {
        $hasUserLogs = Schema::hasTable('user_logs')
            && DB::table('user_logs')->where('user_id', $user->id)->exists();
        if ($hasUserLogs) {
            return true;
        }

        $hasImportOps = Schema::hasTable('import_operation_logs')
            && DB::table('import_operation_logs')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if (!empty($user->matricule)) {
                        $q->orWhere('user_matricule', $user->matricule);
                    }
                })
                ->exists();

        return $hasImportOps;
    }
}
