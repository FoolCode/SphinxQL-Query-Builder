SphinxQL Query Builder
======================

Creating a Query Builder Instance
---------------------------------

You can create an instance by using the following code and passing a configured `Connection` class.

.. code-block:: php

    <?php

    use Foolz\SphinxQL\Drivers\Mysqli\Connection;
    use Foolz\SphinxQL\SphinxQL;

    $conn = new Connection();
    $queryBuilder = SphinxQL::create($conn);

Building a Query
----------------

The `Foolz\\SphinxQL\\SphinxQL` class supports building the following queries: `SELECT`, `INSERT`, `UPDATE`, and `DELETE`. Which sort of query being generated depends on the methods called.

For `SELECT` queries, you would start by invoking the `select()` method:

.. code-block:: php

    $queryBuilder
      ->select('id', 'name')
      ->from('index');

For `INSERT`, `REPLACE`, `UPDATE` and `DELETE` queries, you can pass the index as a parameter into the following methods:

.. code-block:: php

    $queryBuilder
      ->insert('index');

    $queryBuilder
      ->replace('index');

    $queryBuilder
      ->update('index');

    $queryBuilder
      ->delete('index');

.. note::

    You can convert the query builder into its compiled SphinxQL dialect string representation by calling `$queryBuilder->compile()->getCompiled()`.

Security: Bypass Query Escaping
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    SphinxQL::expr($string)

Security: Query Escaping
^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    $queryBuilder
      ->escape($value);

.. code-block:: php

    $queryBuilder
      ->quoteIdentifier($value);

.. code-block:: php

    $queryBuilder
      ->quote($value);

.. code-block:: php

    $queryBuilder
      ->escapeMatch($value);

.. code-block:: php

    $queryBuilder
      ->halfEscapeMatch($value);

WHERE Clause
^^^^^^^^^^^^

The `SELECT`, `UPDATE` and `DELETE` statements supports the `WHERE` clause with the following API methods:


.. code-block:: php

    // WHERE `$column` = '$value'
    $queryBuilder
      ->where($column, $value);

    // WHERE `$column` = '$value'
    $queryBuilder
      ->where($column, '=', $value);

    // WHERE `$column` >= '$value'
    $queryBuilder
      ->where($column, '>=', $value)

    // WHERE `$column` IN ('$value1', '$value2', '$value3')
    $queryBuilder
      ->where($column, 'IN', array($value1, $value2, $value3));

    // WHERE `$column` NOT IN ('$value1', '$value2', '$value3')
    $queryBuilder
      ->where($column, 'NOT IN', array($value1, $value2, $value3));

    // WHERE `$column` BETWEEN '$value1' AND '$value2'
    $queryBuilder
      ->where($column, 'BETWEEN', array($value1, $value2))

.. warning::

    Currently, the SphinxQL dialect does not support the `OR` operator and grouping with parenthesis.

MATCH Clause
^^^^^^^^^^^^

`MATCH` extends the `WHERE` clause and allows for full-text search capabilities.

.. code-block:: php

    $queryBuilder
      ->match($column, $value, $halfEscape = false);

By default, all inputs are automatically escaped by the query builder. The usage of `SphinxQL::expr($value)` can be used to bypass the default query escaping and quoting functions in place during query compilation. The `$column` argument accepts a string or an array. The `$halfEscape` argument, if set to `true`, will not escape and allow the usage of the following special characters: `-`, `|`, and `"`.

SET Clause
^^^^^^^^^^

.. code-block:: php

    $queryBuilder
      ->set($associativeArray);

.. code-block:: php

    $queryBuilder
      ->value($column1, $value1)
      ->value($colume2, $value2);

.. code-block:: php

    $queryBuilder
      ->columns($column1, $column2, $column3)
      ->values($value1_1, $value2_1, $value3_1)
      ->values($value1_2, $value2_2, $value3_2);

GROUP BY Clause
^^^^^^^^^^^^

The `GROUP BY` supports grouping by multiple columns or computed expressions.

.. code-block:: php

    // GROUP BY $column
    $queryBuilder
      ->groupBy($column);

WITHIN GROUP ORDER BY
^^^^^^^^^^^^^^^^^^^^^

The `WITHIN GROUP ORDER BY` clause allows you to control how the best row within a group will be selected.

.. code-block:: php

    // WITHIN GROUP ORDER BY $column [$direction]
    $queryBuilder
      ->withinGroupOrderBy($column, $direction = null);

ORDER BY Clause
^^^^^^^^^^^^^^^

Unlike in regular SQL, only column names (not expressions) are allowed.

.. code-block:: php

    // ORDER BY $column [$direction]
    $queryBuilder
      ->orderBy($column, $direction = null);

OFFSET and LIMIT Clause
^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    // LIMIT $offset, $limit
    $queryBuilder
      ->limit($offset, $limit);

.. code-block:: php

    // LIMIT $limit
    $queryBuilder
      ->limit($limit);

OPTION Clause
^^^^^^^^^^^^^

The `OPTION` clause allows you to control a number of per-query options.

.. code-block:: php

    // OPTION $name = $value
    $queryBuilder
      ->option($name, $value);

COMPILE
-------

You can have the query builder compile the generated query for debugging with the following method:

.. code-block:: php

    $queryBuilder
      ->compile();

This can be used for debugging purposes and obtaining the resulting query generated.

EXECUTE
-------

In order to run the query, you must invoke the `execute()` method so that the query builder can compile the query for execution and then return the results of the query.

.. code-block:: php

    $queryBuilder
      ->execute();
