<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2015-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

namespace FacturaScripts\Core\Model;

/**
 * Regularización del stock de un almacén de un artículos en una fecha concreta.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class RegularizacionStock
{
    use Base\ModelTrait;

    /**
     * Clave primaria.
     *
     * @var int
     */
    public $id;

    /**
     * ID del stock, en el modelo stock.
     *
     * @var int
     */
    public $idstock;

    /**
     * TODO
     *
     * @var float
     */
    public $cantidadini;

    /**
     * TODO
     *
     * @var float
     */
    public $cantidadfin;

    /**
     * Código del almacén destino.
     *
     * @var string
     */
    public $codalmacendest;

    /**
     * TODO
     *
     * @var string
     */
    public $fecha;

    /**
     * TODO
     *
     * @var string
     */
    public $hora;

    /**
     * TODO
     *
     * @var string
     */
    public $motivo;

    /**
     * Nick del usuario que ha realizado la regularización.
     *
     * @var string
     */
    public $nick;

    public function tableName()
    {
        return 'lineasregstocks';
    }

    public function primaryColumn()
    {
        return 'id';
    }

    /**
     * Resetea los valores de todas las propiedades modelo.
     */
    public function clear()
    {
        $this->id = null;
        $this->idstock = null;
        $this->cantidadini = 0;
        $this->cantidadfin = 0;
        $this->codalmacendest = null;
        $this->fecha = date('d-m-Y');
        $this->hora = date('H:i:s');
        $this->motivo = '';
        $this->nick = null;
    }

    /**
     * Esta función es llamada al crear la tabla del modelo. Devuelve el SQL
     * que se ejecutará tras la creación de la tabla. útil para insertar valores
     * por defecto.
     *
     * @return string
     */
    public function install()
    {
        new Stock();

        return '';
    }
}
