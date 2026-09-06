<?php

namespace Tests\Unit\Suppliers;

use App\Suppliers\Parsing\StructuredSupplierFeedParser;
use Tests\TestCase;

class StructuredSupplierFeedParserTest extends TestCase
{
    public function test_it_parses_bom_csv_and_skips_malformed_rows(): void
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBFsku,name,price\nA1,Oil Filter,12.50\nBROKEN,ROW\nA2,Air Filter,18.00\n");
        rewind($stream);

        $rows = iterator_to_array((new StructuredSupplierFeedParser)->rows($stream, 'csv'), false);
        fclose($stream);

        $this->assertCount(2, $rows);
        $this->assertSame('A1', $rows[0]['sku']);
        $this->assertSame('Air Filter', $rows[1]['name']);
    }

    public function test_it_parses_nested_json_items(): void
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, json_encode(['payload' => ['items' => [['id' => 1], ['id' => 2]]]], JSON_THROW_ON_ERROR));
        rewind($stream);

        $rows = iterator_to_array((new StructuredSupplierFeedParser)->rows($stream, 'json', ['items_path' => 'payload.items']), false);
        fclose($stream);

        $this->assertSame([['id' => 1], ['id' => 2]], $rows);
    }

    public function test_it_parses_xml_nodes_without_network_access(): void
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, '<root><item><sku>A1</sku><name>Filter</name></item><item><sku>A2</sku><name>Belt</name></item></root>');
        rewind($stream);

        $rows = iterator_to_array((new StructuredSupplierFeedParser)->rows($stream, 'xml', ['items_xpath' => '//item']), false);
        fclose($stream);

        $this->assertCount(2, $rows);
        $this->assertSame('A2', $rows[1]['sku']);
    }
}
