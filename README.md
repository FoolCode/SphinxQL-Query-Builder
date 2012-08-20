Query Builder for SphinxQL
==========================

## Methods

#### Where

Classic WHERE, works with Sphinx filters and fulltext. _OR is not yet implemented in SphinxQL_.

    $sq->where('column', 'value');
    // WHERE `column` = 'value'

    $sq->where('column', '=', 'value');
    // WHERE `column` = 'value'

    $sq->where('column', '>=', 'value')
    // WHERE `column` >= 'value'

    $sq->where('column', 'IN', array('value1', 'value2', 'value3'));
    // WHERE `column` IN ('value1', 'value2', 'value3')

    $sq->where('column', 'BETWEEN', array('value1', 'value2'))
    // WHERE `column` BETWEEN 'value1' AND 'value2'
    // WHERE `example` BETWEEN 10 AND 100

