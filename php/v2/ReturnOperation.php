<?php
declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
	public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    //public function doOperation(): void сигнатура метода не соответствут объявлению в классе родителе, фатальная ошибка
    //(Цыкломатическая сложность кода 27 (max 10), Сложность npath 626688 (max 200), число строк кода 133 (max 100)
    //это значит, что код лучше разбить на мелкие фукции для улучшения читаемости и поддержки
    //
    //Добавить обработку входных даных
    //Дать более понятные имена переменным
    public function doOperation(): array
    {
        $request = (array)$this->getRequest('data');
        
        if(empty($request['resellerId']) || !isset($request['notificationType']))
        {
		throw new \Exception('Required data is missing');
	}
        
        $resellerId = (int)$request['resellerId'] ?? 0;
        $notificationType = (int)$request['notificationType'] ?? 0;
        
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if ($resellerId===0) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result; 
        }

        if ($notificationType===0) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        $clientId = (int) $request['clientId'] ?? 0;
        $client = Contractor::getById($clientId);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('Client not found!', 400);
        }

        $clientFullName = $client->getFullName() ?: $client->name;
   
		$creatorId = (int) $request['creatorId'] ?? 0;
        $creator = Employee::getById($creatorId);
        if ($creator === null) {
            throw new \Exception('Creator not found!', 400);
        }

		$expertId = (int) $request['expertId'] ?? 0;
        $expert = Employee::getById($expertId);
        if ($expert === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($request['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => Status::getName((int)$request['differences']['from']),
                    'TO'   => Status::getName((int)$request['differences']['to']),
                ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID'       => (int)$request['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$request['complaintNumber'],
            'CREATOR_ID'         => $creatorId,
            'CREATOR_NAME'       => $creator->getFullName(),
            'EXPERT_ID'          => $expertId,
            'EXPERT_NAME'        => $expert->getFullName(),
            'CLIENT_ID'          => $clientId,
            'CLIENT_NAME'        => $clientFullName,
            'CONSUMPTION_ID'     => (int)$request['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$request['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$request['agreementNumber'],
            'DATE'               => (string)$request['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($request['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $client->email,
                           'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$request['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$request['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
                
            }
        }

        return $result; 
    }
}
