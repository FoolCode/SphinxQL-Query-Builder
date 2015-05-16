SphinxQL Query Builder Helper
=============================

.. code-block:: php

    Helper::create($conn)
      ->showMeta();

.. code-block:: php

    Helper::create($conn)
      ->showWarnings();

.. code-block:: php

    Helper::create($conn)
      ->showStatus();

.. code-block:: php

    Helper::create($conn)
      ->showTables();

.. code-block:: php

    Helper::create($conn)
      ->showVariables();

.. code-block:: php

    Helper::create($conn)
      ->showSessionVariables();

.. code-block:: php

    Helper::create($conn)
      ->showGlobalVariables();

.. code-block:: php

    Helper::create($conn)
      ->setVariable($variable, $value, $global = false);

.. code-block:: php

    Helper::create($conn)
      ->callSnippets($data, $index, $extra = array());

.. code-block:: php

    Helper::create($conn)
      ->callKeywords($text, $index, $hits = null);

.. code-block:: php

    Helper::create($conn)
      ->describe($index);

.. code-block:: php

    Helper::create($conn)
      ->createFunction($name, $returns, $soname);

.. code-block:: php

    Helper::create($conn)
      ->dropFunction($name);

.. code-block:: php

    Helper::create($conn)
      ->attachIndex($diskIndex, $rtIndex);

.. code-block:: php

    Helper::create($conn)
      ->flushRtIndex($index);
