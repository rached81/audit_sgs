<?php

namespace App\Services;

use Illuminate\Support\Str;

class ColumnMapper
{
    /**
     * Map file headers to required database columns using fuzzy matching.
     *
     * @param array $fileHeaders List of headers from the file
     * @param array $requiredColumns List of required database columns (slugs)
     * @return array Mapping result ['mapping' => [], 'confidence' => [], 'missing' => []]
     */
    public function mapHeaders(array $fileHeaders, array $requiredColumns): array
    {
        $mapping = [];
        $confidence = [];
        $usedHeaders = [];

        // Dictionary of common synonyms for our specific domain
        $synonyms = [
            'initial' => ['debut', 'start', 'depart', 'qte_init', 'stock_init', 'stock_depart', 'ouv'],
            'entree' => ['in', 'achat', 'reception', 'entrees', 'qte_entree'],
            'sortie' => ['out', 'vente', 'conso', 'consommation', 'sorties', 'qte_sortie'],
            'finale' => ['fin', 'final', 'solde', 'restant', 'qte_fin', 'stock_fin', 'actuel'],
            'article' => ['code', 'ref', 'reference', 'art', 'id', 'item', 'numero'],
            'designation' => ['libelle', 'des', 'description', 'nom', 'intitule'],
            'pump' => ['pmp', 'cout_unitaire', 'prix_moyen', 'pu'],
            'valeur' => ['montant', 'total_valeur', 'val', 'valorisation'],
        ];

        // Normalisation helper
        $normalize = function ($str) {
            return Str::slug($str, '');
        };

        $normalizedFileHeaders = [];
        foreach ($fileHeaders as $index => $header) {
            $normalizedFileHeaders[$index] = $normalize($header);
        }

        foreach ($requiredColumns as $reqCol) {
            $bestMatch = null;
            $bestScore = -1; // Higher is better
            $bestHeaderIndex = null;

            $reqColNorm = $normalize($reqCol);
            
            // 1. Exact Match
            foreach ($normalizedFileHeaders as $index => $fileHeaderNorm) {
                if ($fileHeaderNorm === $reqColNorm) {
                    $bestMatch = $fileHeaders[$index];
                    $bestHeaderIndex = $index;
                    $bestScore = 100;
                    break;
                }
            }

            // 2. Contains Match & Synonyms (if no exact match)
            if ($bestScore < 100) {
                foreach ($normalizedFileHeaders as $index => $fileHeaderNorm) {
                    if (in_array($index, $usedHeaders)) continue;

                    // Direct containment
                    if (str_contains($fileHeaderNorm, $reqColNorm) || str_contains($reqColNorm, $fileHeaderNorm)) {
                        $currentScore = 80;
                         if ($currentScore > $bestScore) {
                            $bestMatch = $fileHeaders[$index];
                            $bestHeaderIndex = $index;
                            $bestScore = $currentScore;
                        }
                    }

                    // Synonyms
                    if (isset($synonyms[$reqCol])) {
                        foreach ($synonyms[$reqCol] as $syn) {
                            $synNorm = $normalize($syn);
                             if (str_contains($fileHeaderNorm, $synNorm)) {
                                $currentScore = 75; // Slightly lower than direct containment
                                if ($currentScore > $bestScore) {
                                    $bestMatch = $fileHeaders[$index];
                                    $bestHeaderIndex = $index;
                                    $bestScore = $currentScore;
                                }
                            }
                            
                            // Levenshtein on synonyms
                             $lev = levenshtein($fileHeaderNorm, $synNorm);
                             $maxLength = max(strlen($fileHeaderNorm), strlen($synNorm));
                             $similarity = 100 - (($lev / $maxLength) * 100);

                             if ($similarity > 80) { // High similarity to synonym
                                 $currentScore = 70;
                                 if ($currentScore > $bestScore) {
                                     $bestMatch = $fileHeaders[$index];
                                     $bestHeaderIndex = $index;
                                     $bestScore = $currentScore;
                                 }
                             }
                        }
                    }
                    
                    // Direct Levenshtein on column name
                     $lev = levenshtein($fileHeaderNorm, $reqColNorm);
                     $maxLength = max(strlen($fileHeaderNorm), strlen($reqColNorm));
                     $similarity = 100 - (($lev / $maxLength) * 100);
                     
                     if ($similarity > 70) {
                         $currentScore = 60; // Fuzzy match
                         if ($currentScore > $bestScore) {
                             $bestMatch = $fileHeaders[$index];
                             $bestHeaderIndex = $index;
                             $bestScore = $currentScore;
                         }
                     }
                }
            }

            if ($bestMatch !== null) {
                $mapping[$reqCol] = $bestMatch;
                $confidence[$reqCol] = $bestScore;
                if ($bestScore >= 90) { // Only mark as used if we are fairly confident
                    $usedHeaders[] = $bestHeaderIndex; 
                }
            } else {
                $mapping[$reqCol] = ''; // No match found
                $confidence[$reqCol] = 0;
            }
        }

        return [
            'mapping' => $mapping,
            'confidence' => $confidence,
            'file_headers' => $fileHeaders
        ];
    }
}
