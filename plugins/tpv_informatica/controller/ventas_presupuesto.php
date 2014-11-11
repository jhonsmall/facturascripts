<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014  Francesc Pineda Segarra  shawe.ewahs@gmail.com
  * Copyright (C) 2015  Javier Trujillo Jimenez  javier.trujillo.jimenez@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'base/fs_pdf.php';
require_model('articulo.php');
require_model('asiento.php');
require_model('cliente.php');
require_model('ejercicio.php');
require_model('pedido_cliente.php');
require_model('familia.php');
require_model('fs_var.php');
require_model('impuesto.php');
require_model('linea_presupuesto_cliente.php');
require_model('partida.php');
require_model('presupuesto_cliente.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('subcuenta.php');
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

class ventas_presupuesto extends fs_controller
{
   public $agente;
   public $cliente;
   public $cliente_s;
   public $ejercicio;
   public $familia;
   public $impuesto;
   public $nuevo_presupuesto_url;
   public $presupuesto;
   public $serie;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, ucfirst(FS_PRESUPUESTO), 'ventas', FALSE, FALSE);
   }
   
   protected function process()
   {
      $this->ppage = $this->page->get('ventas_presupuestos');
      $this->agente = FALSE;
      
      /// desactivamos la barra de botones
      $this->show_fs_toolbar = FALSE;
      
      $presupuesto = new presupuesto_cliente();
      $this->presupuesto = FALSE;
      $this->cliente = new cliente();
      $this->cliente_s = FALSE;
      $this->ejercicio = new ejercicio();
      $this->familia = new familia();
      $this->impuesto = new impuesto();
      $this->nuevo_presupuesto_url = FALSE;
      $this->serie = new serie();
      
      /**
       * Comprobamos si el usuario tiene acceso a nueva_venta,
       * necesario para poder añadir líneas.
       */
      if( $this->user->have_access_to('nueva_venta', FALSE) )
      {
         $nuevoprep = $this->page->get('nueva_venta');
         if($nuevoprep)
            $this->nuevo_presupuesto_url = $nuevoprep->url();
      }
      
      if( isset($_POST['idpresupuesto']) )
      {
         $this->presupuesto = $presupuesto->get($_POST['idpresupuesto']);
         $this->modificar();
      }
      else if( isset($_GET['id']) )
      {
         $this->presupuesto = $presupuesto->get($_GET['id']);
         if (new DateTime() > new DateTime($this->presupuesto->finoferta))
         {
            $this->new_advice("Fecha validez del ".FS_PRESUPUESTO." vencida.");
         }
      }
      
      if( $this->presupuesto AND isset($_GET['imprimir']) )
      {
         if($_GET['imprimir'] == 'simple')
         {
            $this->generar_pdf_simple();
         }
         else
         {
            $this->generar_pdf_cuartilla();
         }
      }
      else if( $this->presupuesto )
      {
         $this->page->title = $this->presupuesto->codigo;
         
         /// cargamos el agente
         if( !is_null($this->presupuesto->codagente) )
         {
            $agente = new agente();
            $this->agente = $agente->get($this->presupuesto->codagente);
         }
         
         /// cargamos el cliente
         $this->cliente_s = $this->cliente->get($this->presupuesto->codcliente);
         
         /// comprobamos el presupuesto
         if( $this->presupuesto->full_test() )
         {
            if( isset($_GET['pedir']) AND isset($_GET['petid']) AND is_null($this->presupuesto->idpedido) )
            {
               if( $this->duplicated_petition($_GET['petid']) )
               {
                  $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
               }
               else
                  $this->generar_pedido();
            }
         }
      }
      else
         $this->new_error_msg("¡".ucfirst(FS_PRESUPUESTO)." de cliente no encontrado!");
   }
   
   public function url()
   {
      if( !isset($this->presupuesto) )
      {
         return parent::url();
      }
      else if($this->presupuesto)
      {
         return $this->presupuesto->url();
      }
      else
         return $this->page->url();
   }
   
   private function modificar()
   {
      $this->presupuesto->observaciones = $_POST['observaciones'];
      $this->presupuesto->numero2 = $_POST['numero2'];
      
      if( is_null($this->presupuesto->idpedido) )
      {
         /// obtenemos los datos del ejercicio para acotar la fecha
         $eje0 = $this->ejercicio->get( $this->presupuesto->codejercicio );
         if($eje0)
         {
            $this->presupuesto->fecha = $eje0->get_best_fecha($_POST['fecha'], TRUE);
            $this->presupuesto->finoferta = $_POST['finoferta'];
            $this->presupuesto->hora = $_POST['hora'];
         }
         else
            $this->new_error_msg('No se encuentra el ejercicio asociado al ".FS_PRESUPUESTO."');
         
         /// ¿cambiamos el cliente?
         if($_POST['cliente'] != $this->presupuesto->codcliente)
         {
            $cliente = $this->cliente->get($_POST['cliente']);
            if($cliente)
            {
               foreach($cliente->get_direcciones() as $d)
               {
                  if($d->domfacturacion)
                  {
                     $this->presupuesto->codcliente = $cliente->codcliente;
                     $this->presupuesto->cifnif = $cliente->cifnif;
                     $this->presupuesto->nombrecliente = $cliente->nombrecomercial;
                     $this->presupuesto->apartado = $d->apartado;
                     $this->presupuesto->ciudad = $d->ciudad;
                     $this->presupuesto->coddir = $d->id;
                     $this->presupuesto->codpais = $d->codpais;
                     $this->presupuesto->codpostal = $d->codpostal;
                     $this->presupuesto->direccion = $d->direccion;
                     $this->presupuesto->provincia = $d->provincia;
                     break;
                  }
               }
            }
            else
               die('No se ha encontrado el cliente.');
         }
         else
            $cliente = $this->cliente->get($this->presupuesto->codcliente);
         
         $serie = $this->serie->get($this->presupuesto->codserie);
         
         /// ¿cambiamos la serie?
         if($_POST['serie'] != $this->presupuesto->codserie)
         {
            $serie2 = $this->serie->get($_POST['serie']);
            if($serie2)
            {
               $this->presupuesto->codserie = $serie2->codserie;
               $this->presupuesto->irpf = $serie2->irpf;
               $this->presupuesto->new_codigo();
               
               $serie = $serie2;
            }
         }
         
         if( isset($_POST['numlineas']) )
         {
            $numlineas = intval($_POST['numlineas']);
            
            $this->presupuesto->neto = 0;
            $this->presupuesto->totaliva = 0;
            $this->presupuesto->totalirpf = 0;
            $this->presupuesto->totalrecargo = 0;
            $lineas = $this->presupuesto->get_lineas();
            $articulo = new articulo();
            
            /// eliminamos las líneas que no encontremos en el $_POST
            foreach($lineas as $l)
            {
               $encontrada = FALSE;
               for($num = 0; $num <= $numlineas; $num++)
               {
                  if( isset($_POST['idlinea_'.$num]) )
                  {
                     if($l->idlinea == intval($_POST['idlinea_'.$num]))
                     {
                        $encontrada = TRUE;
                        break;
                     }
                  }
               }
               if( !$encontrada )
               {
                  if( !$l->delete() )
                     $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
               }
            }
            
            /// modificamos y/o añadimos las demás líneas
            for($num = 0; $num <= $numlineas; $num++)
            {
               $encontrada = FALSE;
               if( isset($_POST['idlinea_'.$num]) )
               {
                  foreach($lineas as $k => $value)
                  {
                     /// modificamos la línea
                     if($value->idlinea == intval($_POST['idlinea_'.$num]))
                     {
                        $encontrada = TRUE;
                        $lineas[$k]->cantidad = floatval($_POST['cantidad_'.$num]);
                        $lineas[$k]->pvpunitario = floatval($_POST['pvp_'.$num]);
                        $lineas[$k]->dtopor = floatval($_POST['dto_'.$num]);
                        $lineas[$k]->dtolineal = 0;
                        $lineas[$k]->pvpsindto = ($value->cantidad * $value->pvpunitario);
                        $lineas[$k]->pvptotal = ($value->cantidad * $value->pvpunitario * (100 - $value->dtopor)/100);
                        $lineas[$k]->descripcion = $_POST['desc_'.$num];
                        
                        $lineas[$k]->codimpuesto = NULL;
                        $lineas[$k]->iva = 0;
                        $lineas[$k]->recargo = 0;
                        $lineas[$k]->irpf = 0;
                        if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                        {
                           $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                           if($imp0)
                              $lineas[$k]->codimpuesto = $imp0->codimpuesto;
                           
                           $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                           $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                           
                           if($lineas[$k]->iva > 0)
                              $lineas[$k]->irpf = $this->presupuesto->irpf;
                        }
                        
                        if( $lineas[$k]->save() )
                        {
                           $this->presupuesto->neto += $value->pvptotal;
                           $this->presupuesto->totaliva += $value->pvptotal * $value->iva/100;
                           $this->presupuesto->totalirpf += $value->pvptotal * $value->irpf/100;
                           $this->presupuesto->totalrecargo += $value->pvptotal * $value->recargo/100;
                        }
                        else
                           $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                        break;
                     }
                  }
                  
                  /// añadimos la línea
                  if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                  {
                     $art0 = $articulo->get( $_POST['referencia_'.$num] );
                     if($art0)
                     {
                        $linea = new linea_presupuesto_cliente();
                        $linea->referencia = $art0->referencia;
                        $linea->descripcion = $_POST['desc_'.$num];
                        
                        if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                        {
                           $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                           if($imp0)
                              $linea->codimpuesto = $imp0->codimpuesto;
                           
                           $linea->iva = floatval($_POST['iva_'.$num]);
                           $linea->recargo = floatval($_POST['recargo_'.$num]);
                           
                           if($linea->iva > 0)
                              $linea->irpf = $this->presupuesto->irpf;
                        }
                        
                        $linea->idpresupuesto = $this->presupuesto->idpresupuesto;
                        $linea->cantidad = floatval($_POST['cantidad_'.$num]);
                        $linea->pvpunitario = floatval($_POST['pvp_'.$num]);
                        $linea->dtopor = floatval($_POST['dto_'.$num]);
                        $linea->pvpsindto = ($linea->cantidad * $linea->pvpunitario);
                        $linea->pvptotal = ($linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor)/100);
                        
                        if( $linea->save() )
                        {
                           $this->presupuesto->neto += $linea->pvptotal;
                           $this->presupuesto->totaliva += $linea->pvptotal * $linea->iva/100;
                           $this->presupuesto->totalirpf += $linea->pvptotal * $linea->irpf/100;
                           $this->presupuesto->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                        }
                        else
                           $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                     }
                     else
                        $this->new_error_msg("¡Artículo ".$_POST['referencia_'.$num]." no encontrado!");
                  }
               }
            }
            
            /// redondeamos
            $this->presupuesto->neto = round($this->presupuesto->neto, FS_NF0);
            $this->presupuesto->totaliva = round($this->presupuesto->totaliva, FS_NF0);
            $this->presupuesto->totalirpf = round($this->presupuesto->totalirpf, FS_NF0);
            $this->presupuesto->totalrecargo = round($this->presupuesto->totalrecargo, FS_NF0);
            $this->presupuesto->total = $this->presupuesto->neto + $this->presupuesto->totaliva - $this->presupuesto->totalirpf + $this->presupuesto->totalrecargo;
            
            if( !$this->presupuesto->floatcmp($this->presupuesto->total, $_POST['atotal'], FS_NF0) )
            {
               $this->new_error_msg("El total difiere entre el controlador y la vista (".$this->presupuesto->total.
                       " frente a ".$_POST['atotal']."). Debes informar del error.");
            }
         }
      }
      
      if( $this->presupuesto->save() )
      {
         $this->new_message(ucfirst(FS_PRESUPUESTO)." modificado correctamente.");
         $this->new_change(ucfirst(FS_PRESUPUESTO).' Cliente '.$this->presupuesto->codigo, $this->presupuesto->url());
      }
      else
         $this->new_error_msg("¡Imposible modificar el ".FS_PRESUPUESTO."!");
   }
   
   private function generar_pedido()
   {
      $pedido = new pedido_cliente();
      $pedido->apartado = $this->presupuesto->apartado;
      $pedido->automatica = TRUE;
      $pedido->cifnif = $this->presupuesto->cifnif;
      $pedido->ciudad = $this->presupuesto->ciudad;
      $pedido->codagente = $this->presupuesto->codagente;
      $pedido->codalmacen = $this->presupuesto->codalmacen;
      $pedido->codcliente = $this->presupuesto->codcliente;
      $pedido->coddir = $this->presupuesto->coddir;
      $pedido->coddivisa = $this->presupuesto->coddivisa;
      $pedido->tasaconv = $this->presupuesto->tasaconv;
      $pedido->codejercicio = $this->presupuesto->codejercicio;
      $pedido->codpago = $this->presupuesto->codpago;
      $pedido->codpais = $this->presupuesto->codpais;
      $pedido->codpostal = $this->presupuesto->codpostal;
      $pedido->codserie = $this->presupuesto->codserie;
      $pedido->direccion = $this->presupuesto->direccion;
      $pedido->editable = TRUE;
      $pedido->neto = $this->presupuesto->neto;
      $pedido->nombrecliente = $this->presupuesto->nombrecliente;
      $pedido->observaciones = $this->presupuesto->observaciones;
      $pedido->provincia = $this->presupuesto->provincia;
      $pedido->total = $this->presupuesto->total;
      $pedido->totaliva = $this->presupuesto->totaliva;
      $pedido->numero2 = $this->presupuesto->numero2;
      $pedido->irpf = $this->presupuesto->irpf;
      $pedido->porcomision = $this->presupuesto->porcomision;
      $pedido->recfinanciero = $this->presupuesto->recfinanciero;
      $pedido->totalirpf = $this->presupuesto->totalirpf;
      $pedido->totalrecargo = $this->presupuesto->totalrecargo;
      
      /// asignamos la mejor fecha posible, pero dentro del ejercicio
      $eje0 = $this->ejercicio->get($pedido->codejercicio);
      $pedido->fecha = $eje0->get_best_fecha($pedido->fecha);
      
      $regularizacion = new regularizacion_iva();
      
      if( !$eje0->abierto() )
      {
         $this->new_error_msg("El ejercicio está cerrado.");
      }
      else if( $regularizacion->get_fecha_inside($pedido->fecha) )
      {
         $this->new_error_msg("El IVA de ese periodo ya ha sido regularizado.
            No se pueden añadir más ".FS_PEDIDOS." en esa fecha.");
      }
      else if( $pedido->save() )
      {
         $continuar = TRUE;
         foreach($this->presupuesto->get_lineas() as $l)
         {
            $n = new linea_pedido_cliente();
            $n->idpresupuesto = $l->idpresupuesto;
            $n->idpedido = $pedido->idpedido;
            $n->cantidad = $l->cantidad;
            $n->codimpuesto = $l->codimpuesto;
            $n->descripcion = $l->descripcion;
            $n->dtolineal = $l->dtolineal;
            $n->dtopor = $l->dtopor;
            $n->irpf = $l->irpf;
            $n->iva = $l->iva;
            $n->pvpsindto = $l->pvpsindto;
            $n->pvptotal = $l->pvptotal;
            $n->pvpunitario = $l->pvpunitario;
            $n->recargo = $l->recargo;
            $n->referencia = $l->referencia;
            if( !$n->save() )
            {
               $continuar = FALSE;
               $this->new_error_msg("¡Imposible guardar la línea el artículo ".$n->referencia."! ");
               break;
            }
         }
         
         if($continuar)
         {
            $this->presupuesto->idpedido = $pedido->idpedido;
            $this->presupuesto->editable = FALSE;
            if( $this->presupuesto->save() )
            {
               $this->new_message("<a href='".$pedido->url()."'>".ucfirst(FS_PEDIDO).'</a> generado correctamente.');
            }
            else
            {
               $this->new_error_msg("¡Imposible vincular el ".FS_PRESUPUESTO." con el nuevo ".FS_PEDIDO."!");
               if( $pedido->delete() )
               {
                  $this->new_error_msg("El ".FS_PEDIDO." se ha borrado.");
               }
               else
                  $this->new_error_msg("¡Imposible borrar el ".FS_PEDIDO."!");
            }
         }
         else
         {
            if( $pedido->delete() )
            {
               $this->new_error_msg("El ".FS_PEDIDO." se ha borrado.");
            }
            else
               $this->new_error_msg("¡Imposible borrar el ".FS_PEDIDO."!");
         }
      }
      else
         $this->new_error_msg("¡Imposible guardar el ".FS_PEDIDO."!");
   }
   
   private function generar_pdf_simple($archivo = FALSE)
   {
      if( !$archivo )
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', ucfirst(FS_PRESUPUESTO).' '. $this->presupuesto->codigo);
      $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_PRESUPUESTO).' de cliente ' . $this->presupuesto->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->presupuesto->get_lineas();
      if($lineas)
      {
         $linea_actual = 0;
         $lppag = 42;
         $pagina = 1;
         
         /// imprimimos las páginas necesarias
         while( $linea_actual < count($lineas) )
         {
            /// salto de página
            if($linea_actual > 0)
               $pdf_doc->pdf->ezNewPage();
            
            /// ¿Añadimos el logo?
            if( file_exists('tmp/'.FS_TMP_NAME.'logo.png') )
            {
               $pdf_doc->pdf->ezImage('tmp/'.FS_TMP_NAME.'logo.png', 0, 200, 'none');
               $lppag -= 2; /// si metemos el logo, caben menos líneas
            }
            else
            {
               $pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 16, array('justification' => 'center'));
               $pdf_doc->pdf->ezText(FS_CIFNIF.": ".$this->empresa->cifnif, 8, array('justification' => 'center'));
               
               $direccion = $this->empresa->direccion;
               if($this->empresa->codpostal)
                  $direccion .= ' - ' . $this->empresa->codpostal;
               if($this->empresa->ciudad)
                  $direccion .= ' - ' . $this->empresa->ciudad;
               if($this->empresa->provincia)
                  $direccion .= ' (' . $this->empresa->provincia . ')';
               if($this->empresa->telefono)
                  $direccion .= ' - Teléfono: ' . $this->empresa->telefono;
               $pdf_doc->pdf->ezText($direccion, 9, array('justification' => 'center'));
            }
            
            /*
             * Esta es la tabla con los datos del cliente:
             * Presupuesto:             Fecha:
             * Cliente:             CIF/NIF:
             * Dirección:           Teléfonos:
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>".ucfirst(FS_PRESUPUESTO).":</b>",
                   'dato1' => $this->presupuesto->codigo,
                   'campo2' => "<b>Fecha:</b>",
                   'dato2' => $this->presupuesto->fecha
               )
            );
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>Cliente:</b>",
                   'dato1' => $this->presupuesto->nombrecliente,
                   'campo2' => "<b>".FS_CIFNIF.":</b>",
                   'dato2' => $this->presupuesto->cifnif
               )
            );
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>Dirección:</b>",
                   'dato1' => $this->presupuesto->direccion.' CP: '.$this->presupuesto->codpostal.' - '.$this->presupuesto->ciudad.' ('.$this->presupuesto->provincia.')',
                   'campo2' => "<b>Teléfonos:</b>",
                   'dato2' => $this->cliente->telefono1.'  '.$this->cliente->telefono2
               )
            );
            $pdf_doc->save_table(
               array(
                   'cols' => array(
                       'campo1' => array('justification' => 'right'),
                       'dato1' => array('justification' => 'left'),
                       'campo2' => array('justification' => 'right'),
                       'dato2' => array('justification' => 'left')
                   ),
                   'showLines' => 0,
                   'width' => 540,
                   'shaded' => 0
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /*
             * Creamos la tabla con las lineas del presupuesto:
             * 
             * Descripción    PVP   DTO   Cantidad    Importe
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                  'descripcion' => '<b>Descripción</b>',
                  'pvp' => '<b>PVP</b>',
                  'dto' => '<b>DTO</b>',
                  'cantidad' => '<b>Cantidad</b>',
                  'importe' => '<b>Importe</b>'
               )
            );
            $saltos = 0;
            $subtotal = 0;
            $impuestos = array();
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
            {
               if( !isset($impuestos[$lineas[$linea_actual]->iva]) )
                  $impuestos[$lineas[$linea_actual]->iva] = $lineas[$linea_actual]->pvptotal * $lineas[$linea_actual]->iva / 100;
               else
                  $impuestos[$lineas[$linea_actual]->iva] += $lineas[$linea_actual]->pvptotal * $lineas[$linea_actual]->iva / 100;
               
               $fila = array(
                  'descripcion' => substr($lineas[$linea_actual]->descripcion, 0, 45),
                  'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->presupuesto->coddivisa),
                  'dto' => $this->show_numero($lineas[$linea_actual]->dtopor, 0) . " %",
                  'cantidad' => $lineas[$linea_actual]->cantidad,
                  'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->presupuesto->coddivisa)
               );
               
               if($lineas[$linea_actual]->referencia != '0')
                  $fila['descripcion'] = substr($lineas[$linea_actual]->referencia.' - '.$lineas[$linea_actual]->descripcion, 0, 40);
               
               $pdf_doc->add_table_row($fila);
               $saltos++;
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'pvp' => array('justification' => 'right'),
                       'dto' => array('justification' => 'right'),
                       'cantidad' => array('justification' => 'right'),
                       'importe' => array('justification' => 'right')
                   ),
                   'width' => 540,
                   'shaded' => 0
               )
            );
            
            
            /*
             * Rellenamos el hueco que falta hasta donde debe aparecer la última tabla
             */
            if($this->presupuesto->observaciones == '')
            {
               $salto = '';
            }
            else
            {
               $salto = "\n<b>Observaciones</b>: " . $this->presupuesto->observaciones;
               $saltos += count( explode("\n", $this->presupuesto->observaciones) ) - 1;
            }
            
            if($saltos < $lppag)
            {
               for(;$saltos < $lppag; $saltos++)
                  $salto .= "\n";
               $pdf_doc->pdf->ezText($salto, 11);
            }
            else if($linea_actual >= $lineasfact)
               $pdf_doc->pdf->ezText($salto, 11);
            else
               $pdf_doc->pdf->ezText("\n", 11);
            
            
            /*
             * Rellenamos la última tabla de la página:
             * 
             * Página            Neto    IVA   Total
             */
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Página</b>', 'neto' => '<b>Neto</b>',);
            $fila = array(
                'pagina' => $pagina . '/' . ceil(count($lineas) / $lppag),
                'neto' => $this->show_precio($this->presupuesto->neto, $this->presupuesto->coddivisa),
            );
            $opciones = array(
                'cols' => array(
                    'neto' => array('justification' => 'right'),
                ),
                'showLines' => 4,
                'width' => 540
            );
            foreach($impuestos as $i => $value)
            {
               $titulo['iva'.$i] = '<b>IVA '.$i.'%</b>';
               $fila['iva'.$i] = $this->show_precio($value, $this->presupuesto->coddivisa);
               $opciones['cols']['iva'.$i] = array('justification' => 'right');
            }
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->presupuesto->total, $this->presupuesto->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            $pdf_doc->pdf->ezText("\n", 10);
            
            $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text($this->empresa->pie_factura, 153), 0, 1.5);
            
            $pagina++;
         }
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show();
   }
   
   private function generar_pdf_cuartilla()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', ucfirst(FS_PRESUPUESTO).' '. $this->presupuesto->codigo);
      $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_PRESUPUESTO).' de cliente ' . $this->presupuesto->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->presupuesto->get_lineas();
      if($lineas)
      {
         $linea_actual = 0;
         $lppag = 14;
         $pagina = 1;
         
         /// imprimimos las páginas necesarias
         while( $linea_actual < count($lineas) )
         {
            /// salto de página
            if($linea_actual > 0)
               $pdf_doc->pdf->ezNewPage();
            
            /// encabezado
            $texto = "<b>".ucfirst(FS_PRESUPUESTO).":</b> ".$this->presupuesto->codigo."\n".
                    "<b>Fecha:</b> ".$this->presupuesto->fecha."\n".
                    "<b>SR. D:</b> ".$this->presupuesto->nombrecliente;
            $pdf_doc->pdf->ezText($texto, 12, array('justification' => 'right'));
            $pdf_doc->pdf->ezText("\n", 12);
            
            
            /// tabla principal
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'unidades' => '<b>Ud.</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'dto' => '<b>DTO.</b>',
                   'pvp' => '<b>P.U.</b>',
                   'importe' => '<b>Importe</b>'
               )
            );
            $saltos = 0;
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
            {
               $pdf_doc->add_table_row(
                  Array(
                      'unidades' => $lineas[$linea_actual]->cantidad,
                      'descripcion' => $lineas[$linea_actual]->referencia.' - '.$lineas[$linea_actual]->descripcion,
                      'dto' => $this->show_numero($lineas[$linea_actual]->dtopor, 2).' %',
                      'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->presupuesto->coddivisa),
                      'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->presupuesto->coddivisa)
                  )
               );
               
               $linea_actual++;
               $saltos++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 9,
                   'cols' => array(
                       'dto' => array('justification' => 'right'),
                       'pvp' => array('justification' => 'right'),
                       'importe' => array('justification' => 'right')
                   ),
                   'width' => 540,
                   'shadeCol' => array(0.9, 0.9, 0.9)
               )
            );
            
            /// Rellenamos el hueco que falta hasta donde debe aparecer la última tabla
            if($this->presupuesto->observaciones == '')
            {
               $salto = '';
            }
            else
            {
               $salto = "\n<b>Observaciones</b>: " . $this->presupuesto->observaciones;
               $saltos += count( explode("\n", $this->presupuesto->observaciones) ) - 1;
            }
            
            if($saltos < $lppag)
            {
               for(;$saltos < $lppag; $saltos++) { $salto .= "\n"; }
                  $pdf_doc->pdf->ezText($salto, 12);
            }
            else if( $linea_actual >= count($lineas) )
            {
               $pdf_doc->pdf->ezText($salto, 12);
            }
            else
               $pdf_doc->pdf->ezText("\n", 10);
            
            
            /// Escribimos los totales
            $opciones = array('justification' => 'right');
            $neto = '<b>Pag</b>: ' . $pagina . '/' . ceil(count($lineas) / $lppag);
            $neto .= '        <b>Neto</b>: ' . $this->show_precio($this->presupuesto->neto, $this->presupuesto->coddivisa);
            $neto .= '    <b>IVA</b>: ' . $this->show_precio($this->presupuesto->totaliva, $this->presupuesto->coddivisa);
            $neto .= '    <b>Total</b>: ' . $this->show_precio($this->presupuesto->total, $this->presupuesto->coddivisa);
            $pdf_doc->pdf->ezText($neto, 12, $opciones);
            
            $pagina++;
         }
      }
      
      $pdf_doc->show();
   }
   
   private function enviar_email()
   {
      $cliente = $this->cliente->get($this->presupuesto->codcliente);
      
      if( $this->empresa->can_send_mail() AND $cliente )
      {
         if( $_POST['email'] != $cliente->email )
         {
            $cliente->email = $_POST['email'];
            $cliente->save();
         }
         
         /// obtenemos la configuración extra del email
         $mailop = array(
             'mail_host' => 'smtp.gmail.com',
             'mail_port' => '465',
             'mail_user' => '',
             'mail_enc' => 'ssl'
         );
         $fsvar = new fs_var();
         $mailop = $fsvar->array_get($mailop, FALSE);
         
         $filename = 'presupuesto_'.$this->presupuesto->codigo.'.pdf';
         $this->generar_pdf_simple($filename);
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = TRUE;
            $mail->SMTPSecure = $mailop['mail_enc'];
            $mail->Host = $mailop['mail_host'];
            $mail->Port = intval($mailop['mail_port']);
            
            if($mailop['mail_user'] != '')
               $mail->Username = $mailop['mail_user'];
            else
               $mail->Username = $this->empresa->email;
            
            $mail->Password = $this->empresa->email_password;
            $mail->From = $this->empresa->email;
            $mail->FromName = $this->user->nick;
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $this->empresa->nombre . ': Su '.FS_PRESUPUESTO.' '.$this->presupuesto->codigo;
            $mail->AltBody = 'Buenos días, le adjunto su '.FS_PRESUPUESTO.' '.$this->presupuesto->codigo.".\n".$this->empresa->email_firma;
            $mail->WordWrap = 50;
            $mail->MsgHTML( nl2br($_POST['mensaje']) );
            $mail->AddAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            $mail->AddAddress($_POST['email'], $cliente->nombrecomercial);
            $mail->IsHTML(TRUE);
            
            if( $mail->Send() )
               $this->new_message('Mensaje enviado correctamente.');
            else
               $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
         }
         else
            $this->new_error_msg('Imposible generar el PDF.');
      }
   }
}