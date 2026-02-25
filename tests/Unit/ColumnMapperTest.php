<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ColumnMapper;

class ColumnMapperTest extends TestCase
{
    public function test_exact_match()
    {
        $mapper = new ColumnMapper();
        $fileHeaders = ['article', 'designation', 'initial'];
        $required = ['article', 'initial'];

        $result = $mapper->mapHeaders($fileHeaders, $required);

        $this->assertEquals('article', $result['mapping']['article']);
        $this->assertEquals('initial', $result['mapping']['initial']);
        $this->assertEquals(100, $result['confidence']['article']);
    }

    public function test_synonym_match()
    {
        $mapper = new ColumnMapper();
        $fileHeaders = ['Code Article', 'Stock Départ', 'Ventes'];
        $required = ['article', 'initial', 'sortie'];

        $result = $mapper->mapHeaders($fileHeaders, $required);

        // 'Code Article' contains 'article' -> should match 'article'
        $this->assertEquals('Code Article', $result['mapping']['article']);
        
        // 'Stock Départ' is synonym for 'initial'
        $this->assertEquals('Stock Départ', $result['mapping']['initial']);
        
        // 'Ventes' is synonym for 'sortie'
        $this->assertEquals('Ventes', $result['mapping']['sortie']);
    }

    public function test_fuzzy_match()
    {
        $mapper = new ColumnMapper();
        $fileHeaders = ['initail', 'entreee']; // Typo
        $required = ['initial', 'entree'];

        $result = $mapper->mapHeaders($fileHeaders, $required);

        $this->assertEquals('initail', $result['mapping']['initial']);
        $this->assertEquals('entreee', $result['mapping']['entree']);
        $this->assertTrue($result['confidence']['initial'] < 100);
        $this->assertTrue($result['confidence']['initial'] > 50);
    }
}
