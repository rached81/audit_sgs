<?php

namespace App\Services;

use Illuminate\Support\Str;

class ColumnMapper
{
    private array $aliasIndex = [];

    /**
     * @param array $requiredColumns
     * @return void
     */
    private function _buildAliasIndex(array $requiredColumns): void
    {
        $syn = $this->synonyms();
        $idx = [];

        foreach ($requiredColumns as $req) {
            $idx[$this->norm($req)] = $req;

            foreach (($syn[$req] ?? []) as $alias) {
                $idx[$this->norm($alias)] = $req;
            }
        }

        $this->aliasIndex = $idx;
    }

    public function mapHeaders(array $row, array $requiredColumns): array
    {
        // Etape 1: nettoyer les entetes source.
        $headers = array_values(array_map(fn($v) => trim((string) $v), $row));

        // Etape 2: normaliser les entetes non vides.
        $normHeaders = [];
        foreach ($headers as $i => $h) {
            if ($h === '') {
                continue;
            }
            $normHeaders[$i] = $this->norm($h);
        }

        // Etape 3: construire l'index inverse alias -> champ requis.
        $aliasIndex = $this->buildAliasIndex($requiredColumns);

        $mapping = [];
        $confidence = [];
        foreach ($requiredColumns as $req) {
            $mapping[$req] = null;
            $confidence[$req] = 0;
        }

        // Etape 4: appliquer le matching exact/synonyme puis contains.
        foreach ($normHeaders as $idx => $nh) {
            if (isset($aliasIndex[$nh])) {
                $field = $aliasIndex[$nh];
                $isExact = ($nh === $this->norm($field));
                $score = $isExact ? 100 : 95;

                if ($score > $confidence[$field]) {
                    $mapping[$field] = $headers[$idx];
                    $confidence[$field] = $score;
                }
                continue;
            }

            foreach ($aliasIndex as $aliasNorm => $field) {
                if ($confidence[$field] >= 95) {
                    continue;
                }

                if (strlen($aliasNorm) < 3) {
                    continue;
                }

                if (str_contains($nh, $aliasNorm) || str_contains($aliasNorm, $nh)) {
                    if ($confidence[$field] < 85) {
                        $mapping[$field] = $headers[$idx];
                        $confidence[$field] = 85;
                    }
                }
            }
        }

        // Etape 5: fallback fuzzy leger uniquement pour les champs non trouves.
        foreach ($requiredColumns as $req) {
            if ($confidence[$req] >= 85) {
                continue;
            }

            $bestIdx = null;
            $bestScore = 0;

            foreach ($normHeaders as $idx => $nh) {
                if (strlen($nh) < 4) {
                    continue;
                }

                $dist = levenshtein($nh, $req);
                if ($dist <= 2) {
                    $bestIdx = $idx;
                    $bestScore = 70;
                    break;
                }
            }

            if ($bestIdx !== null && $bestScore > $confidence[$req]) {
                $mapping[$req] = $headers[$bestIdx];
                $confidence[$req] = $bestScore;
            }
        }

        // Etape 6: retourner mapping + niveau de confiance par champ.
        return [
            'mapping' => $mapping,
            'confidence' => $confidence,
        ];
    }

    private function norm(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = str_replace(['°', '’', "'", '"', '(', ')', '[', ']', '{', '}', ':', ';', ',', '.', "\n", "\r", "\t"], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return Str::slug($s, '_');
    }

    private function synonyms(): array
    {
        return [
            'article' => [
                'article', 'code_article', 'art', 'artcod', 'code', 'reference', 'ref', 'article_code',
            ],
            'designation' => [
                'designation', 'désignation', 'libelle', 'libellé', 'produit', 'description', 'intitule', 'intitulé',
            ],
            'initial' => [
                'initial', 'stock_initial', 'stock_depart', 'stock_debut', 'depart', 'debut',
                'solde_initial', 'stock_au_debut', 'stock_de_depart', 'qte_init',
            ],
            'entree' => [
                'entree', 'entrée', 'entrees', 'entrées', 'achat', 'achats', 'reception', 'réception', 'in', 'qte_entree',
            ],
            'sortie' => [
                'sortie', 'sorties', 'vente', 'ventes', 'consommation', 'consommations', 'out', 'qte_sortie',
            ],
            'finale' => [
                'finale', 'final', 'stock_final', 'stock_fin', 'fin', 'solde_final',
                'stock_finale', 'stock_a_la_fin', 'qte_fin', 'restant', 'actuel',
            ],
            'pump' => [
                'pump', 'pmp', 'prix_moyen', 'prix_moyen_pondere', 'prix_moyen_pondéré', 'pm', 'p_m_p', 'cout_unitaire', 'pu',
            ],
            'valeur' => [
                'valeur', 'montant', 'valeur_stock', 'total', 'valeur_totale', 'amount', 'total_valeur', 'val', 'valorisation',
            ],
        ];
    }

    private function buildAliasIndex(array $requiredColumns): array
    {
        $syn = $this->synonyms();
        $idx = [];

        foreach ($requiredColumns as $req) {
            $idx[$this->norm($req)] = $req;

            foreach (($syn[$req] ?? []) as $alias) {
                $idx[$this->norm($alias)] = $req;
            }
        }

        return $idx;
    }
}
