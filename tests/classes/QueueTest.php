<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Queue;

class QueueTest extends PHPUnit_Framework_TestCase
{
	public function testQueue()
	{
		$queue = new Queue();
		$queue->setConnectionParams('127.0.0.1', 9306)->connect();

		SphinxQL::forge($queue)->delete()
			->from('rt')
			->where('id', 'IN', array(13, 14, 15))
			->execute();

		SphinxQL::forge($queue)->insert()
			->into('rt')
			->columns('id', 'title', 'content', 'gid')
			->values(13, 'i am getting bored', 'with all this CONTENT', 1)
			->values(14, 'i want a vacation', 'the code is going to break sometime', 2)
			->values(15, 'there\'s no hope in this class', 'just give up', 3)
			->execute();

		$one = SphinxQL::forge($queue)->select()
			->from('rt')
			->where('gid', '=', 1);

		$two = SphinxQL::forge($queue)->select()
			->from('rt')
			->where('gid', '=', 2);

		$result = $queue
			->add($one)
			->add($two)
			->executeBatch();

		$this->assertSame('13', $result[0][0]['id']);
		$this->assertSame('14', $result[1][0]['id']);

		SphinxQL::forge($queue)->delete()
			->from('rt')
			->where('id', 'IN', array(13, 14, 15))
			->execute();
	}
}