<?php

namespace OCA\Files_Sharing\Tests\AppInfo\Migrations;

// FIXME: autoloader fails to load migration
require_once \dirname(\dirname(\dirname(__DIR__))) . "/appinfo/Migrations/Version20210902115435.php";

use Doctrine\DBAL\Schema\Table;
use OCA\Files_Sharing\Migrations\Version20210902115435;
use Test\TestCase;
use Doctrine\DBAL\Schema\Schema;

class Version20210902115435Test extends TestCase {
	public function testExecute() {
		$tablePrefix = 'pr_';
		$migration = new Version20210902115435();
		$table = $this->createMock(Table::class);
		$schema = $this->createMock(Schema::class);
		$schema->method('hasTable')->with('pr_share')->willReturn(true);
		$schema->method('getTable')->with('pr_share')->willReturn($table);
		$table->method('hasColumn')->with('is_quick_link')->willReturn(false);
		$table->expects($this->once())->method('addColumn');

		$this->assertNull($migration->changeSchema($schema, ['tablePrefix' => $tablePrefix]));
	}
}
