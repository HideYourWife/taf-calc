<?

//facade
class GetCalc
{
    private $calc;

    function __construct($data)
    {
        $class_name = $this->get_name_by_id(intval($data['id']));
        $this->calc = new $class_name($data);
    }

    function get_calc()
    {
        return $this->calc;
    }

    protected function get_name_by_id($id)
    {
        switch ($id) {
            case 53:
            case 54: return 'VForm';
            case 122: return 'Carnis';
            case 123: return 'Lameli';
            case 55:
            case 56: return 'GForm';
            case 57: return 'ACarnisElectro';
            case 58: return 'ARomeCarnisElectro';
            case 59: return 'AVertical';
            case 60: return 'AHorizontal';
            case 61:
            case 62:
            case 63:
            case 64:
            case 65:
            case 66:
            case 67:
            case 68: return 'Rolled';
            case 69:
            case 70:
            case 71:
            case 72:
            case 73: return 'RolledDuo';
            case 74: return 'Rome';
            case 75: return 'Plisse';
        }
    }
}


abstract class BossCalc
{
    protected $data = array();
    protected $product = array();
    protected $material = array();
    protected $mat_section = array();
    protected $mat_section_number = 1;
    protected $mat_price = 1;
    protected $quantity = 1;
    protected $result = [];

    function __construct($data)
    {
        $this->data = $data;
        if ($this->data['id'])
            $this->get_product(intval($this->data['id']));
        if ($this->data['material']['id']) {
            $this->get_material(intval($this->data['material']['id']));
            $this->get_mat_subsection(intval($this->material['IBLOCK_SECTION_ID']));
        }
        if (!empty($this->material))
            $this->mat_price = $this->mat_section['UF_PRICE'][intval($this->product['PROPERTIES']['PRICE_GROUP']['VALUE'])-1];
        if (!empty($this->material['PROPERTIES']['STOCK']['VALUE']))
            $this->calculate_stock();
        $this->quantity = intval($this->data['quantity']) ?? 1;
        try {
            $this->check_errors();
        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }
    }

    //------------------------------------

    protected function get_product($id)
    {
        if(!CModule::IncludeModule("iblock")) return;
        $arSelect = Array(
            'ID',
            'IBLOCK_ID',
            'NAME',
            'CODE',
            'DETAIL_PAGE_URL',
            'PROPERTY_*'
        );
        $arFilter = Array('ID' => $id);
        $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $this->product = $arFields;
            $arProps = $ob->GetProperties();
            $this->product['PROPERTIES'] = $arProps;
        }
    }

    protected function get_material($id)
    {
        if(!CModule::IncludeModule("iblock")) return;
        $arSelect = Array(
            'ID',
            'IBLOCK_ID',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'IBLOCK_NAME',
            'IBLOCK_CODE',
            'PROPERTY_*',
        );
        $arFilter = Array('ID' => $id);
        $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $this->material = $arFields;
            $arProps = $ob->GetProperties();
            $this->material['PROPERTIES'] = $arProps;
        }
    }

    protected function get_mat_subsection($id)
    {
        $arFilter = array('IBLOCK_ID' => $this->material['IBLOCK_ID'], 'ID' => $id);
        $rsSect = CIBlockSection::GetList(array('left_margin' => 'asc'), $arFilter, false, array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'UF_*'));
        while ($arSect = $rsSect->GetNext())
        {
            $this->mat_section = $arSect;
        }
        // close your yes...
        preg_match('!\d+!', $this->mat_section['NAME'], $matches);      //
        $this->mat_section_number = $matches[0];                                // im mad..., im very mad
    }

    protected function get_furniture()
    {
        if(!empty($this->data['furniture_color']['id'])){
            $arSelect = Array(
                'ID',
                'NAME',
                'CODE',
                'IBLOCK_ID',
                'PROPERTY_MARK_UP',
                'IBLOCK_NAME',
                'IBLOCK_CODDE',
            );
            $arFilter = Array(
                'IBLOCK_ID' => intval($this->data['furniture_color']['iblock_id']),
                'ID' => intval($this->data['furniture_color']['id']),
                "ACTIVE" => "Y"
            );
            $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $res = [
                    'id' => $arFields['ID'],
                    'iblock_id' => $arFields['IBLOCK_ID'],
                    'name' => $arFields['NAME'],
                    'iblock_name' => $arFields['IBLOCK_NAME'],
                    'iblock_code' => $arFields['IBLOCK_CODE'],
                    'code' => $arFields['CODE'],
                    'mark_up' => $arFields['PROPERTY_MARK_UP_VALUE'],
                ];
                $this->result['PROPERTIES'][$res['id']] = $res;
                return $res;
            }
        }
        return false;
    }

    protected function get_mount_type()
    {
        return $this->get_type_by_name('mount_type');
    }

    protected function get_magnets()
    {
        return $this->get_type_by_name('magnets');
    }

    protected function get_control_type()
    {
        return $this->get_type_by_name('control_type');
    }

    protected function get_slide_type()
    {
        return $this->get_type_by_name('slide_type');
    }

    protected function get_profile()
    {
        return $this->get_type_by_name('profile');
    }

    protected function get_chargable()
    {
        return $this->get_type_by_name('chargable');
    }

    protected function get_electric_drive_type()
    {
        if(!empty($this->data['electric_drive_type']['id'])){
            $arSelect = Array(
                'ID',
                'NAME',
                'CODE',
                'IBLOCK_ID',
                'PROPERTY_PRICE',
                'IBLOCK_NAME',
                'IBLOCK_CODE',
                'PROPERTY_TABLE_PRICE_COL',
            );
            $arFilter = Array(
                'IBLOCK_ID' => intval($this->data['electric_drive_type']['iblock_id']),
                'ID' => intval($this->data['electric_drive_type']['id']),
                "ACTIVE" => "Y"
            );
            $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $res = [
                    'id' => $arFields['ID'],
                    'iblock_id' => $arFields['IBLOCK_ID'],
                    'name' => $arFields['NAME'],
                    'iblock_name' => $arFields['IBLOCK_NAME'],
                    'iblock_code' => $arFields['IBLOCK_CODE'],
                    'code' => $arFields['CODE'],
                    'price' => $arFields['PROPERTY_PRICE_VALUE'],
                    'table_price_col' => $arFields['PROPERTY_TABLE_PRICE_COL_VALUE'],
                ];
                $this->result['PROPERTIES'][$res['id']] = $res;
                return $res;
            }
        }
        return false;
    }

    protected function get_type_by_name($name)
    {
        if(!empty($this->data[$name]['id'])){
            $arSelect = Array(
                'ID',
                'NAME',
                'CODE',
                'IBLOCK_ID',
                'PROPERTY_PRICE',
                'IBLOCK_NAME',
                'IBLOCK_CODE',
            );
            $arFilter = Array(
                'IBLOCK_ID' => intval($this->data[$name]['iblock_id']),
                'ID' => intval($this->data[$name]['id']),
                "ACTIVE" => "Y"
            );
            $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $res = [
                    'id' => $arFields['ID'],
                    'iblock_id' => $arFields['IBLOCK_ID'],
                    'name' => $arFields['NAME'],
                    'code' => $arFields['CODE'],
                    'price' => $arFields['PROPERTY_PRICE_VALUE'],
                    'iblock_name' => $arFields['IBLOCK_NAME'],
                    'iblock_code' => $arFields['IBLOCK_CODE'],
                ];
                $this->result['PROPERTIES'][$res['id']] = $res;
                return $res;
            }
        }
        return false;
    }

    protected function add_element_by_id($id, $iblock_id)
    {
        $id = intval($id);
        $iblock_id = intval($iblock_id);
        $arSelect = Array(
            'ID',
            'NAME',
            'CODE',
            'IBLOCK_ID',
            'PROPERTY_PRICE',
            'IBLOCK_NAME',
            'IBLOCK_CODE',
        );
        $arFilter = Array(
            'IBLOCK_ID' => $iblock_id,
            'ID' => $id,
            "ACTIVE" => "Y"
        );
        $res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $res = [
                'id' => $arFields['ID'],
                'iblock_id' => $arFields['IBLOCK_ID'],
                'name' => $arFields['NAME'],
                'code' => $arFields['CODE'],
                'price' => $arFields['PROPERTY_PRICE_VALUE'],
                'iblock_name' => $arFields['IBLOCK_NAME'],
                'iblock_code' => $arFields['IBLOCK_CODE'],
            ];
            $this->result['PROPERTIES'][$res['id']] = $res;
            return $res;
        }
    }

    protected function check_errors()
    {
        $min_width = $this->product['PROPERTIES']['MIN_WIDTH']['VALUE'];
        $max_width = $this->product['PROPERTIES']['MAX_WIDTH']['VALUE'];
        $min_height = $this->product['PROPERTIES']['MIN_HEIGHT']['VALUE'];
        $max_height = $this->product['PROPERTIES']['MAX_HEIGHT']['VALUE'];
        $width = floatval($this->data['width'])*1000;
        $height = floatval($this->data['height'])*1000;
        if (($min_width && $width) && $width < $min_width) {
            throw new Exception('Изделие должно быть больше минимально допустимого значения по ширине');
        }
        if (($max_width && $width) && $width > $max_width) {
            throw new Exception('Изделие должно быть меньше максимально допустимого значения по ширине');
        }
        if (($max_height && $height) && $height > $max_height) {
            throw new Exception('Изделие должно быть меньше максимально допустимого значения по высоте');
        }
        if (($min_height && $height) && $height < $min_height) {
            throw new Exception('Изделие должно быть больше минимально допустимого значения по высоте');
        }
    }

    protected function calculate_stock()
    {
        $stock = intval($this->material['PROPERTIES']['STOCK']['VALUE']);
        $mat_price = $this->mat_price;
        $this->mat_price = $mat_price - ($mat_price/100 * $stock);
    }

    protected function round_up($number, $precision = 2)
    {
        $fig = (int) str_pad('1', $precision, '0');
        return (ceil($number * $fig) / $fig);
    }

    protected function get_result($result)
    {
        $this->result['product'] = [
            'id' => $this->product['ID'],
            'iblock_id' => $this->product['IBLOCK_ID'],
            'name' => $this->product['NAME'],
            'code' => $this->product['CODE'],
            'url' => $this->product['DETAIL_PAGE_URL'],
        ];
        if (!empty($this->material['ID'])) {
            $this->result['PROPERTIES'][$this->material['ID']] = [
                'id' => $this->material['ID'],
                'iblock_id' => $this->material['IBLOCK_ID'],
                'name' => $this->material['NAME'],
                'code' => $this->material['CODE'],
                'iblock_name' => $this->material['IBLOCK_NAME'],
                'iblock_code' => $this->material['IBLOCK_CODE'],
                'sort' => 3,
            ];
            if (!empty($this->data['material']['cut_direction'])) {
                $cut_direction = htmlspecialchars($this->data['material']['cut_direction']);
                $cut_name = $cut_direction == 'width' ? 'По ширине' : 'По высоте';

                $this->result['PROPERTIES'][9999] = [
                    'id' => 9999,
                    'iblock_id' => 9999,
                    'name' => $cut_name,
                    'code' => 'cut_direction',
                    'iblock_name' => 'Направление раскроя',
                    'iblock_code' => 'cut_direction',
                    'sort' => 4,
                ];
            }
        }


        if (!empty($this->data['width'])) {
            $width = [
                "iblock_name" => 'Ширина',
                "iblock_code" => 'width',
                "name" => ''.(floatval($this->data['width'])*1000).' мм',
                "sort" => 1,
            ];
            $this->result['PROPERTIES'][] = $width;
        }
        if (!empty($this->data['height'])) {
            $height = [
                "iblock_name" => 'Высота',
                "iblock_code" => 'height',
                "name" => ''.(floatval($this->data['height'])*1000).' мм',
                "sort" => 2,
            ];
            $this->result['PROPERTIES'][] = $height;
        }

        foreach ($this->data as $key => $data) {
            if (!array_key_exists($data['id'], $this->result['PROPERTIES']) && $data['id']>0) {
                $this->add_element_by_id($data['id'], $data['iblock_id']);
            }
        }

        $this->result['PROPERTIES'] = array_filter($this->result['PROPERTIES']);

        $this->result['quantity'] = $this->quantity;
        $this->result['calculated_price'] = $result;
        return $this->result;
    }
}



// VForm class
class VForm extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $carnis_price = intval($this->product['PROPERTIES']['CARNIS_PRICE']['VALUE']);
        $carnis_total = $width * $carnis_price;
        $mat_price = $this->mat_price;
        $area = $width * $height;
        $total = ($area * $mat_price + $carnis_total) * $this->quantity;
        $total = intval($total+0.5);
        return $this->get_result($total);
    }
}

// GForm class
class GForm extends BossCalc
{
    function calculate()
    {
        $mark_up = false;
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $area = $width * $height;

        if (intval($this->data['id']) == 55 && $area < 0.8) {
            $area = 0.8;
        } elseif (intval($this->data['id']) == 56 && $area < 1) {
            $area = 1;
        }

        if (intval($this->data['id']) == 56) {
            $furniture = $this->get_furniture();
            $mark_up = $furniture['mark_up'];
        }
        if (intval($this->data['id']) == 55) {
            $mount_type = $this->get_mount_type();
            $magnets = $this->get_magnets();
        }

        $mat_price = $this->mat_price;


        if ($mark_up != 'да' && intval($this->data['id']) == 56)
            $total = $area * $mat_price * 1.5;
        else
            $total = $area * $mat_price;

        $total +=  intval($mount_type['price']) ?? 0;
        if ($this->data['magnets']['quantity'])
            $total += intval($magnets['price']) * intval($this->data['magnets']['quantity']);
        $total *= $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// Carnis class
class Carnis extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $carnis_price = intval($this->product['PROPERTIES']['CARNIS_PRICE']['VALUE']);
        $total = $width * $carnis_price * $this->quantity;
        $total = intval($total+0.5);
        return $this->get_result($total);
    }
}

// Lameli class
class Lameli extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $min_lamel = $this->product['PROPERTIES']['MIN_ORDER']['VALUE'];
        $max_lamel = $this->product['PROPERTIES']['MAX_ORDER']['VALUE'];

        $area = $width * ($height + 0.04);
        $mat_price = $this->mat_price;
        $total = ($area * $mat_price) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// Rolled class
class Rolled extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $mat_price = $this->mat_price;

        if ($this->data['material']['cut_direction'] == 'width') {
            $total = ($width * $mat_price);
        } else {
            if ($width > $height) {
                $total = ($height * $mat_price);
            } else {
                $total = ($height * $mat_price) * 0.8;
            }
        }

        if (intval($this->data['id']) == 62 || intval($this->data['id']) == 63) {
            $furniture = $this->get_furniture();
            $mark_up = $furniture['mark_up'];
        }

        if ($mark_up != 'да' && (intval($this->data['id']) == 62 || intval($this->data['id']) == 63)) {
            $qualifier =  \Bitrix\Main\Config\Option::get( "askaron.settings", "UF_FURNITURE");
            $total *= (1 + intval($qualifier)/100);
        }


        $total = $total * $this->quantity;
        $total = intval($total+0.5);
        return $this->get_result($total);
    }
}

// RolledDuo class
class RolledDuo extends BossCalc
{
    function calculate()
    {
        $mark_up = false;
        $height = floatval($this->data['height']);
        $mat_price = $this->mat_price;

        if (intval($this->data['id']) == 70) {
            $furniture = $this->get_furniture();
            $mark_up = $furniture['mark_up'];
        }

        if ($mark_up != 'да' && intval($this->data['id']) == 70) {
            $qualifier =  \Bitrix\Main\Config\Option::get( "askaron.settings", "UF_FURNITURE");
            $total = ($height * $mat_price * (1+intval($qualifier)/100)) * $this->quantity;
        } else
            $total = ($height * $mat_price) * $this->quantity;

        $total = intval($total+0.5);
        return $this->get_result($total);
    }
}

// ACarnisElectro class
class ACarnisElectro extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $width = $this->round_up($width);
        $file_name = $this->product['PROPERTIES']['FILE_NAME']['VALUE'];
        $data = json_read($_SERVER['DOCUMENT_ROOT'].'/'.$file_name);
        $electric_drive = $this->get_electric_drive_type();
        $slide = $this->get_slide_type();
        $price = $data[$electric_drive['table_price_col']][strval($width)];
        $control_type = $this->get_control_type();
        $cargable = $this->get_chargable();
        $total = (intval($price) + $control_type['price'] + $slide['price'] + $cargable['price']) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// AVertical class
class AVertical extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $carnis_price = intval($this->product['PROPERTIES']['CARNIS_PRICE']['VALUE']);
        $electro_drive = $this->get_electric_drive_type();
        $total = ($width * $carnis_price + intval($electro_drive['price'])) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// ARomeCarnisElectro class
class ARomeCarnisElectro extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $width = $this->round_up($width);
        $file_name = $this->product['PROPERTIES']['FILE_NAME']['VALUE'];
        $data = json_read($_SERVER['DOCUMENT_ROOT'].'/'.$file_name);
        $control_type = $this->get_control_type();
        $electric_drive = $this->get_electric_drive_type();
        $chargable = $this->get_chargable();
        $carnis_price = $data[strval($width)];
        $total = (intval($carnis_price) + $electric_drive['price'] + $chargable['price'] + $control_type['price']) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// Rome class
class Rome extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $carnis_price = intval($this->product['PROPERTIES']['CARNIS_PRICE']['VALUE']) * $width;
        $mat_price = $this->mat_price;
        $mat_price *= $height;
        $total = ($mat_price + $carnis_price) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}

// Plisse class
class Plisse extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $width = $this->round_up($width);
        $height = $this->round_up($height);
        $mount_type = $this->get_mount_type();
        $profile_color = $this->get_profile();
        $file_name = $this->product['PROPERTIES']['FILE_NAME']['VALUE'];
        $data = json_read($_SERVER['DOCUMENT_ROOT'].'/'.$file_name);
        $mat_section = $this->mat_section_number;
        $mat_price = $data[$mat_section][$height*100][$width*100];
        $total = ($mat_price + $mount_type['price'] + $profile_color['price']) * $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }

}

// AHorizontal class
class AHorizontal extends BossCalc
{
    function calculate()
    {
        $width = floatval($this->data['width']);
        $height = floatval($this->data['height']);
        $area = $width * $height;
        if ($area < 1) $area = 1;
        $mat_price = $this->mat_price;
        $total = $mat_price * $area;
        $control_type = $this->get_control_type();
        $electric_drive = $this->get_electric_drive_type();
        $chargable = $this->get_chargable();
        $total += $control_type['price'] + $electric_drive['price'] + $chargable['price'];
        $total += $this->quantity;
        $total = intval($total+0.5);

        return $this->get_result($total);
    }
}
?>