# -*- coding: utf-8 -*-
import sys, os
sys.path.insert(0, os.path.abspath('.'))

#needs_sphinx = '1.0'

extensions = ['sphinx.ext.autodoc', 'sphinx.ext.viewcode']

templates_path = ['_templates']

source_suffix = '.rst'
master_doc = 'index'

# General information about the project.
project = u'SphinxQL Query Builder'
copyright = u'2012-2015, FoolCode'

version = '1.0.0'
release = version

exclude_patterns = ['_build', 'html', 'doctrees']
add_function_parentheses = True
add_module_names = True
show_authors = False
pygments_style = 'sphinx'
modindex_common_prefix = ['foolfuuka']
html_theme = 'default'
html_static_path = ['_static']
htmlhelp_basename = 'FoolFuukaDoc'

from sphinx.highlighting import lexers
from pygments.lexers.web import JsonLexer
from pygments.lexers.web import PhpLexer

lexers['json'] = JsonLexer(startinline=True)
lexers['php'] = PhpLexer(startinline=True)
