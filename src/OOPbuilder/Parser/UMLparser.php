<?php
/**
 * This file is part of the OOPbuilder project
 *
 * @author Wouter J <http://wouterj.nl>
 * @license Creative Commons Attribution Share-Alike <http://creativecommons.org/licenses/by-sa/3.0/>
 */

namespace OOPbuilder\Parser;

use OOPbuilder\Helper;

/**
 * A parser class for parsing UML files.
 */
class UMLparser implements ParserInterface
{
	/**
     * {@inheritdoc}
     *
	 * @return array An assoc-array with all data
	 */
	public function parse($data)
	{
		$info = array();
        $parts = array(
            'classes' => array(),
            'interfaces' => array(),
        );
        $i = -1;

		foreach (preg_split('/((\r?\n)|(\n?\r))/', $data) as $line) {
			if ((0 !== $line) && empty($line)) {
				continue;
			}

			if ('  ' !== substr($line, 0, 2)) {
                if ('<<' == substr($line, 0, 2)) {
                    $parts['interfaces'][++$i] = array($line);
                } else {
                    $parts['classes'][++$i] = array($line);
                }
            } else {
                if (isset($parts['classes'][$i])) {
                    $parts['classes'][$i][] = $line;
                } else {
                    $parts['interfaces'][$i][] = $line;
                }
            }
        }

        foreach ($parts['classes'] as $i => $class) {
            $info[$i] = $this->parseClass($class);
        }
        foreach ($parts['interfaces'] as $i => $interface) {
            $info[$i] = $this->parseInterface($interface);
        }
        sort($info);

		return $info;
	}

	/**
	 * Parses an interface.
	 *
	 * @param array $data The array with the lines of the interface
	 *
	 * @return array An assoc-array with all interface data
	 */
    public function parseInterface($data)
    {
        $interface = array(
            'type' => 'interface',
            'name' => '',
            'methods' => array(),
        );

        foreach ($data as $line) {
			if ('  ' !== substr($line, 0, 2)) {
				$interface['name'] = trim($line, '<>');
                if (1 < count($children = explode('::', $line))) {
                    $interface['name'] = trim($children[0]);
                    $interface['implements'] = trim($children[1]);
                }
            } else {
                $interface['methods'][] = $this->parseMethod($line);
            }
        }

        return $interface;
    }

	/**
	 * Parses a class.
	 *
	 * @param array $data The array with the lines of the class
	 *
	 * @return array An assoc-array with all class data
	 */
	public function parseClass($data)
	{
        $class = array(
            'type' => 'class',
            'name' => '',
            'properties' => array(),
            'methods' => array(),
        );

		foreach ($data as $line) {
			if ('  ' !== substr($line, 0, 2)) {
				$class['name'] = $line;

                // implements
                if (1 < count($children = explode('::', $line))) {
                    $class['name'] = trim($children[0]);
                    $class['implements'] = trim($children[1]);
                }
                
                // extends
                if(1 < count($children = explode(':', $line))) {
                    $class['name'] = trim($children[0]);
                    $class['extends'] = trim($children[1]);
                }
			} else {
                if (')' === substr(trim($line), -1)) {
                    $class['methods'][] = $this->parseMethod(substr($line, 2));
                } else {
                    $class['properties'][] = $this->parseProperty(substr($line, 2));
                }
			}
		}

		return $class;
	}

	/**
	 * Parses a property.
	 *
	 * @param string $str The property line
	 *
	 * @return array An assoc-array with all data of the property
	 */
    public function parseProperty($str)
    {
        $property = array(
            'access' => $this->parseAccess(substr($str, 0, 1)),
        );

        $value = explode('=', $str);
        $property['name'] = substr(trim($value[0]), 2);
        if (1 < count($value)) {
            $property['value'] = trim(Helper::parseValue(trim($value[1])));
        }

        return $property;
    }

	/**
	 * Parses a method.
	 *
	 * @param string $str The method line
	 * 
	 * @return array An assoc-array with all data of the method
	 */
	public function parseMethod($str)
	{
		$method = array(
			'access' => $this->parseAccess(substr($str, 0, 1)),
            'arguments' => array(),
		);
		preg_match('/(?<=\s).*?(?=\()/', $str, $name);
		$method['name'] = $name[0];

        if (preg_match('/\((.+?)\)$/', $str, $args)) {
            $method['arguments'] = $this->parseArguments($args[1]);
        }

		return $method;
	}

	/**
	 * Parses arguments.
	 *
	 * @param string $args All arguments in one line with ', ' between them
	 * 
	 * @return array An assoc-array with all arguments and data
	 */
	public function parseArguments($args)
    {
        $argumentsList = array();

        $arguments = explode(', ', $args);

        foreach ($arguments as $arg) {
            @list($argName, $argValue) = explode('=', $arg);
            $argumentsList[] = array(
                'name' => trim($argName),
                'value' => ($argValue !== 0 && !empty($argValue)
                                ? Helper::parseValue(trim($argValue))
                                : null
                           ),
            );
        }

        return $argumentsList;
	}

	/**
	 * Parses the access.
	 *
	 * @param string $str The access
	 *
	 * @return string The access, parsed
	 */
	public function parseAccess($str)
	{
		$umlAccess = array(
			'+' => 'public',
			'#' => 'protected',
			'-' => 'private',
		);

		if (isset($umlAccess[$str])) {
			return $umlAccess[$str];
		} else {
			return (Helper::is_access($str)
						? $str
						: 'public'
				   );
		}
	}
}
