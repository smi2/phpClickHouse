 <?php

use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 */
class BindFormatTest extends TestCase
{
    /**
     * @var \ClickHouseDB\Client
     */
    private $db;

    /**
     * @var
     */
    private $tmp_path;

    /**
     * @throws Exception
     */
    public function setUp()
    {

        $this->db = new ClickHouseDB\Client(['username'=>'','password'=>'','port'=>8123,'host'=>'123']);

    }

    public function testBindselectAsync()
    {
        $this->setUp();

        // ---- 1
        $a=$this->db->selectAsync("SELECT :a, :a2", [
            "a" => "a",
            "a2" => "a2"
        ]);
        $this->assertEquals("SELECT 'a', 'a2' FORMAT JSON",$a->sql());


        // ---- 2
        $a=$this->db->selectAsync("SELECT {a}, {b}", [
            "a" => ":b",
            "b" => ":B"
        ]);

        $this->assertEquals("SELECT ':B', :B FORMAT JSON",$a->sql());






    }


}
