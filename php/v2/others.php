<?php
declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
 //в классе нет конструкторов, это значит, что он может быть создан без каких либо параметров
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id; 
    public $type;
    public $name;

    public static function getById(int $resellerId): self
    {
        //Создается новый экземпляр класса, но данные из реальной БД или другого источника не извлекаются
        //Следовательно метод подделыет результат
        /*Советую такой вариант
            $instance = new self();
            $instance->id = $resellerId;
            //Инициализируем остальные данные
            return $instance; 
         */
         //нет конструктора с одним параметром
        return new self($resellerId); // fakes the getById method 
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class Status
{
    //переменная $name нигде не используется, можно удалить
    public $id, $name;

    public static function getName(int $id): string
    {
/*
        $a = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $a[$id];
       */
       //лучше так:
       
        $statusNames = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $statusNames[$id];
    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        //return $_REQUEST[$pName];
        return $_REQUEST[$pName] ?? null;
    }
}

function getResellerEmailFrom($resellerId)
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com']; //правильно "someemail"
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}
