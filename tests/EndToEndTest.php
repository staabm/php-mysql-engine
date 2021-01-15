<?php
namespace Vimeo\MysqlEngine\Tests;

class EndToEndTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown() : void
    {
        \Vimeo\MysqlEngine\Server::reset();
    }

    public function testSelectEmptyResults()
    {
        $pdo = self::getConnectionToFullDB();

        $query = $pdo->prepare("SELECT id FROM `video_game_characters` WHERE `id` > :id");
        $query->bindValue(':id', 100);
        $query->execute();

        $this->assertSame([], $query->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testSelectFetchAssoc()
    {
        $pdo = self::getConnectionToFullDB();

        $query = $pdo->prepare("SELECT id FROM `video_game_characters` WHERE `id` > :id ORDER BY `id` ASC");
        $query->bindValue(':id', 14);
        $query->execute();

        $this->assertSame(
            [
                ['id' => '15'],
                ['id' => '16']
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testSelectFetchAssocConverted()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare("SELECT id FROM `video_game_characters` WHERE `id` > :id ORDER BY `id` ASC");
        $query->bindValue(':id', 14);
        $query->execute();

        $this->assertSame(
            [
                ['id' => 15],
                ['id' => 16]
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testDefaultNullTimestamp()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare("SELECT `deleted_on` FROM `video_game_characters` WHERE `id` = 1");
        $query->bindValue(':id', 14);
        $query->execute();

        $this->assertSame(
            [
                ['deleted_on' => null],
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testAliasWithType()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare("SELECT SUM(`a`) FROM (SELECT `id` as `a` FROM `video_game_characters`) `foo`");
        $query->bindValue(':id', 14);
        $query->execute();

        $this->assertSame(
            [
                ['SUM(`a`)' => 136]
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testAliasName()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare("SELECT `a` FROM (SELECT SUM(`id`) as `a` FROM `video_game_characters`) `foo`");
        $query->bindValue(':id', 14);
        $query->execute();

        $this->assertSame(
            [
                ['a' => 136]
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testLeftJoin()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare(
            "SELECT SUM(`powerups`) as `p`
            FROM `video_game_characters`
            LEFT JOIN `character_tags` ON `character_tags`.`character_id` = `video_game_characters`.`id`"
        );
        $query->execute();

        $this->assertSame(
            [
                ['p' => 21]
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testLeftJoinWithCount()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare(
            'SELECT `name`,
                    `tag_totals`.`c`
                FROM `video_game_characters`
                LEFT JOIN (
                    SELECT COUNT(*) as `c`, `character_tags`.`character_id`
                    FROM `character_tags`
                    GROUP BY `character_tags`.`character_id`) AS `tag_totals`
                ON `tag_totals`.`character_id` = `video_game_characters`.`id`
                ORDER BY `id`
                LIMIT 5'
        );
        $query->execute();

        $this->assertSame(
            [
                ['name' => 'mario', 'c' => 2],
                ['name' => 'luigi', 'c' => 3],
                ['name' => 'sonic', 'c' => null],
                ['name' => 'earthworm jim', 'c' => null],
                ['name' => 'bowser', 'c' => 2]
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function testMaxValueAliasedToColumnName()
    {
        $pdo = self::getConnectionToFullDB(false);

        $query = $pdo->prepare(
            'SELECT `character_id`, MAX(`id`) as `id`
                FROM `character_tags`
                GROUP BY `character_id`
                LIMIT 3'
        );
        $query->execute();

        $this->assertSame(
            [
                ['character_id' => 1, 'id' => 2],
                ['character_id' => 2, 'id' => 5],
                ['character_id' => 5, 'id' => 7],
            ],
            $query->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    private static function getConnectionToFullDB(bool $emulate_prepares = true) : \PDO
    {
        $pdo = new \Vimeo\MysqlEngine\FakePdo('mysql:foo;dbname=test;');

        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $emulate_prepares);

        // create table
        $pdo->prepare(file_get_contents(__DIR__ . '/fixtures/create_table.sql'))->execute();

        // insertData
        $pdo->prepare(file_get_contents(__DIR__ . '/fixtures/bulk_character_insert.sql'))->execute();
        $pdo->prepare(file_get_contents(__DIR__ . '/fixtures/bulk_enemy_insert.sql'))->execute();
        $pdo->prepare(file_get_contents(__DIR__ . '/fixtures/bulk_tag_insert.sql'))->execute();

        return $pdo;
    }
}