<?php

declare(strict_types=1);

namespace EDITest;

use EDI\Parser;

class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testCustomStripRegex()
    {
        $p = new Parser();
        $p->setStripRegex("/[\x{0001}-\x{001F}\x{0080}-\x{00C0}]/");
        $string = "LOC+11+ITGOA'MEA+WT++KGM:9040'";
        $test = $p->loadString($string);

        $expected = [["LOC", "11", "ITGOA"], ["MEA", "WT", "", ["KGM", "9040"]]];
        $this->assertSame($expected, $test);
    }

    public function testMessageUnwrap()
    {
        $p = new Parser();
        $string = "LOC+11+ITGOA'MEA+WT++KGM:9040'";
        $test = $p->loadString($string);

        $expected = [["LOC", "11", "ITGOA"], ["MEA", "WT", "", ["KGM", "9040"]]];
        $this->assertSame($expected, $test);
    }

    public function testArrayUnwrap()
    {
        $arr = ["LOC+11+ITGOA'MEA+WT++KGM:9040'"];
        $test = (new Parser($arr))->get();
        $expected = [["LOC", "11", "ITGOA"], ["MEA", "WT", "", ["KGM", "9040"]]];
        $this->assertSame($expected, $test);
    }

    public function testGetRawSegments()
    {
        $txt = "LOC+11+ITGOA'MEA+WT++KGM:9040'";
        $test = (new Parser($txt))->getRawSegments();
        $expected = ["LOC+11+ITGOA'", "MEA+WT++KGM:9040'"];
        $this->assertSame($expected, $test);
    }

    public function testParseSimple()
    {
        $p = new Parser();
        $array = ["LOC+9+VNSGN'", "LOC+11+ITGOA'", "MEA+WT++KGM:9040'"];
        $expected = [["LOC", "9", "VNSGN"], ["LOC", "11", "ITGOA"], ["MEA", "WT", "", ["KGM", "9040"]]];
        $result = $p->parse($array);
        $this->assertSame($expected, $result);
    }

    public function testEscapedSegment()
    {

        $string = "EQD+CX??DU12?+3456+2?:0'";
        $expected = [["EQD", "CX?DU12+3456", "2:0"]];
        $result = (new Parser($string))->get();
        $this->assertSame($expected, $result);
    }

    /** @dataProvider multipleEscapedSegmentsProvider */
    public function testMultipleEscapedSegments($string,$expected)
    {
        $result = (new Parser($string))->get();
        $this->assertSame($expected,$result);
    }

    public function multipleEscapedSegmentsProvider()
    {
        return [
            ["EQD+CX??DU12?+3456+2?:0'",      [["EQD", "CX?DU12+3456", "2:0"]]],
            ["EQD+CX????DU12?+3456+2?:0'",    [["EQD", "CX??DU12+3456", "2:0"]]],
            ["EQD+CX??????DU12?+3456+2?:0'",  [["EQD", "CX???DU12+3456", "2:0"]]],
            ["EQD+CX????????DU12?+3456+2?:0'",[["EQD", "CX????DU12+3456", "2:0"]]],
            ["EQD+CX??DU12?+3456+2?:0??'",    [["EQD", "CX?DU12+3456", "2:0?"]]],
            ["EQD+CX??DU12?+3456+2?:0????'",  [["EQD", "CX?DU12+3456", "2:0??"]]],
            ["EQD+CX??DU12?+3456+2?:0??????'",[["EQD", "CX?DU12+3456", "2:0???"]]],
            ["??EQD+CX??DU12?+3456+2?:0'",    [["?EQD", "CX?DU12+3456", "2:0"]]],
            ["????EQD+CX??DU12?+3456+2?:0'",  [["??EQD", "CX?DU12+3456", "2:0"]]],
            ["??????EQD+CX??DU12?+3456+2?:0'",[["???EQD", "CX?DU12+3456", "2:0"]]],
            ["EQD??+CX??DU12?+3456+2?:0'",    [["EQD?", "CX?DU12+3456", "2:0"]]],
            ["EQD????+CX??DU12?+3456+2?:0'",  [["EQD??", "CX?DU12+3456", "2:0"]]],
            ["EQD??????+CX??DU12?+3456+2?:0'",[["EQD???", "CX?DU12+3456", "2:0"]]],
            ["EQD+??CX??DU12?+3456+2?:0'",    [["EQD", "?CX?DU12+3456", "2:0"]]],
            ["EQD+????CX??DU12?+3456+2?:0'",  [["EQD", "??CX?DU12+3456", "2:0"]]],
            ["EQD+??????CX??DU12?+3456+2?:0'",[["EQD", "???CX?DU12+3456", "2:0"]]],
        ];
    }

    public function testFileOk()
    {
        $string = file_get_contents(__DIR__ . '/../files/example_order_ok.edi');

        for ($i = 0; $i < 100; $i++) { // keep for simple performance tests
            $errors = (new Parser($string))->errors();
        }
        $this->assertSame([], $errors);

        $data = json_encode((new Parser($string))->get());
        $this->assertContains('Sup 1:10', $data);
        $this->assertNotContains('Konzentrat:o', $data);
        $this->assertContains('"Rindfleischsuppe Konzentrat","o', $data);
    }

    public function testFileError()
    {
        $string = file_get_contents(__DIR__ . '/../files/example_order_error.edi');
        $errors = (new Parser($string))->errors();
        $this->assertSame(
            [
                0 => 'There\'s a character not escaped with ? in the data; string :::H-Vollmilch 3,5%  ?*?*Marke?*?*:1l Tertra mit Drehverschluss',
            ]
            , $errors
        );
    }

    public function testNotPrintableCharacter()
    {
        $string = "EQD+CèèèXDU12?+3456+2?:0'";
        $expected = [["EQD", "CXDU12+3456", "2:0"]];
        $p = new Parser($string);
        $result = $p->get();
        $experror = "There's a not printable character on line 1: EQD+CèèèXDU12?+3456+2?:0'";
        $error = $p->errors();
        $this->assertSame($expected, $result);
        $this->assertContains($experror, $error);
    }

    public function testNotEscapedSegment()
    {
        $string = "EQD+CX?DU12?+3456+2?:0'";
        $expected = [["EQD", "CX?DU12+3456", "2:0"]];
        $p = new Parser($string);
        $result = $p->get();
        $experror = "There's a character not escaped with ? in the data; string CX?DU12?+3456";
        $error = $p->errors();
        $this->assertSame($expected, $result);
        $this->assertContains($experror, $error);
    }

    public function testSegmentWithMultipleSingleQuotes()
    {
        $string = ["EQD+CX'DU12?+3456+2?:0'", "EQD+CXDU12?+3456+2?:0'"];
        $p = new Parser();
        $result = $p->parse($string);
        $experror = "There's a ' not escaped in the data; string EQD+CX'DU12?+3456+2?:0";
        $error = $p->errors();
        $this->assertContains($experror, $error);
    }

    public function testArrayInputNoErrors()
    {
        $arr = ["LOC+9+VNSGN'", "LOC+11+ITGOA'"];
        $result = (new Parser($arr))->errors();
        $this->assertEmpty($result);
    }

    public function testArrayInputEmptyLine()
    {
        $arr = ["LOC+9+VNSGN'", "", "LOC+11+ITGOA'"];
        $result = (new Parser($arr))->errors();
        $this->assertEmpty($result);
    }

    public function testLoadFile()
    {
        $result = (new Parser(__DIR__ . "/../files/example.edi"))->errors();
        $this->assertEmpty($result);
    }

    public function testLoadWrappedFile()
    {
        $result = (new Parser(__DIR__ . "/../files/example_wrapped.edi"))->errors();
        $this->assertEmpty($result);
    }

    public function testUNHWithoutMessageType()
    {
        $arr = ["UNH+123", "LOC+9+VNSGN'", "LOC+11+ITGOA'"];
        $p = new Parser($arr);
        $this->assertNull($p->getMessageFormat());
        $this->assertNull($p->getMessageDirectory());
    }

    public function testUNHData()
    {
        $arr = ["UNH+1452515553811+COARRI:D:95B:UN:ITG13'"];
        $p = new Parser($arr);
        $this->assertSame("COARRI", $p->getMessageFormat());
        $this->assertSame("95B", $p->getMessageDirectory());
    }

    public function testUNHDataOnlyFormat()
    {
        $arr = ["UNH+1452515553811+COARRI'"];
        $p = new Parser($arr);
        $this->assertSame("COARRI", $p->getMessageFormat());
        $this->assertNull($p->getMessageDirectory());
    }

    public function testUNAString()
    {
        $result = (new Parser(["UNA:+.? '", "UNH+123", "LOC+9+VNSGN'"]))->errors();
        $this->assertEmpty($result);
    }

    /*
    public function testBigFile()
    {
        $p = new Parser();
        $p->load(__DIR__ . "/../files/example_big.edi");
        $result = $p->errors();
        $this->assertSame([], $result);
    }
    */

    public function testReleaseCharacter()
    {
        $p = new Parser();
        $loaded = $p->load(__DIR__ . "/../files/example_release_character.edi");
        $result = $p->errors();
        $this->assertEmpty($result);

        $this->assertSame($loaded[5][2], 'NO MORE FLIGHTS 1');
        $this->assertSame($loaded[6][2], 'NO MORE FLIGHTS 2?');
        $this->assertSame($loaded[7][2], 'NO MORE \' FLIGHTS 3');
        $this->assertSame($loaded[8][2], 'NO MORE ? FLIGHTS 3');
        $this->assertSame($loaded[9][2], 'NO MORE ?\' FLIGHTS 3');

        $this->assertSame($loaded[10][2], 'FIELD 1');
        $this->assertSame($loaded[10][3], 'FIELD 2');

        $this->assertSame($loaded[11][2], 'FIELD 1?');
        $this->assertSame($loaded[11][3], 'FIELD 2');

        $this->assertSame($loaded[12][2], 'FIELD 1?+FIELD 2');

        $this->assertSame($loaded[13][2][0], 'FIELD 1.1');
        $this->assertSame($loaded[13][2][1], 'FIELD 1.2');

        $this->assertSame($loaded[14][2][0], 'FIELD 1.1?');
        $this->assertSame($loaded[14][2][1], 'FIELD 1.2');

        $this->assertSame($loaded[15][2], 'FIELD 1.1?:FIELD 1.2');
    }

}
