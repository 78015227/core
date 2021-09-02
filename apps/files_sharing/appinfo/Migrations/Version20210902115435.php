<?php
namespace OCA\Files_Sharing\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/**
 * Add is_quick_link column.
 */
class Version20210902115435 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("${prefix}share")) {
			$table = $schema->getTable("{$prefix}share");
			if (!$table->hasColumn('is_quick_link')) {
				$table->addColumn('is_quick_link', Types::SMALLINT, [
					'default' => 0,
					'unsigned' => true,
					'notnull' => true,
				]);
			}
		}
	}
}
