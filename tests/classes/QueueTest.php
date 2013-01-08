<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\Queue;

class QueueTest extends PHPUnit_Framework_TestCase
{

	public function testQueue()
	{
		$res = Sphinxql::insert()
			->into('rt')
			->columns('id', 'title', 'content', 'gid')
			->values(13, 'i am getting bored', 'with all this CONTENT', 1)
			->values(14, 'i want a vacation', 'the code is going to break sometime', 2)
			->values(15, 'there\'s no hope in this class', 'just give up', 3)
			->execute();

		$first = Sphinxql::select()
			->from('rt')
			->where('gid', '=', 1);

		$second = Sphinxql::select()
			->from('rt')
			->where('gid', '=', 2);

		$queue = new Queue();

		$result = $queue->add($first)
			->add($second)
			->execute();

		$this->assertSame('13', $result[0][0]['id']);
		$this->assertSame('14', $result[1][0]['id']);

		Sphinxql::delete()
			->from('rt')
			->where('id', 'IN', array(13, 14, 15))
			->execute();
	}

}