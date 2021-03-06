<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017  Francesc Pineda Segarra  francesc.pineda.segarra@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Base\DataBase;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Clase para "tracear" las consultas SQL.
 *
 * @author Francesc Pineda Segarra <francesc.pineda.segarra@gmail.com>
 */
class DataBaseCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * Array con las consultas realizadas
     *
     * @var array
     */
    protected $queries;

    /**
     * DataBaseCollector constructor.
     *
     * @param array $queries
     */
    public function __construct($queries)
    {
        $this->queries = $queries;
    }

    /**
     * Called by the DebugBar when data needs to be collected
     *
     * @return array Collected data
     */
    public function collect()
    {
        $queries = [];
        $totalExecTime = 0;
        foreach ($this->queries as $q) {
            $queries[] = [
                'sql' => $q['message'],
                'duration' => 0,
                'duration_str' => 0,
            ];
            $totalExecTime += 0;
        }

        return [
            'nb_statements' => count($queries),
            'accumulated_duration' => $totalExecTime,
            'statements' => $queries,
        ];
    }

    /**
     * Returns the unique name of the collector
     *
     * @return string
     */
    public function getName()
    {
        return 'db';
    }

    /**
     * Returns a hash where keys are control names and their values
     * an array of options as defined in {@see DebugBar\JavascriptRenderer::addControl()}
     *
     * @return array
     */
    public function getWidgets()
    {
        return [
            'database' => [
                'icon' => 'database',
                'tooltip' => 'Database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'db',
                'default' => '[]',
            ],
            'database:badge' => [
                'map' => 'db.nb_statements',
                'default' => 0,
            ],
        ];
    }

    /**
     * Devuelve los assets necesarios
     *
     * @return array
     */
    public function getAssets()
    {
        $basePath = '../../../../../../';

        return [
            'css' => $basePath . 'Core/View/CSS/phpdebugbar.custom-widget.css',
            'js' => $basePath . 'Core/View/JS/phpdebugbar.custom-widget.js',
        ];
    }
}
