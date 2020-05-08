<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 5 2018
 *
 */
namespace MikoPBX\AdminCabinet\Controllers;

use MikoPBX\AdminCabinet\Forms\ConferenceRoomEditForm;
use MikoPBX\Common\Models\{ConferenceRooms, Extensions};


class ConferenceRoomsController extends BaseController
{


    /**
     * Построение списка конференц комнат
     */
    public function indexAction(): void
    {
        $records             = ConferenceRooms::find();
        $this->view->records = $records;
    }


    /**
     * Карточка редактирования конференц комнаты
     *
     * @param string|NULL $uniqid
     */
    public function modifyAction(string $uniqid = null): void
    {
        $record = ConferenceRooms::findFirstByUniqid($uniqid);
        if ($record === null) {
            $record            = new ConferenceRooms();
            $record->uniqid    = strtoupper('CONFERENCE-' . md5(time()));
            $record->extension = Extensions::getNextFreeApplicationNumber();
        }
        $this->view->form      = new ConferenceRoomEditForm($record);
        $this->view->represent = $record->getRepresent();

    }


    /**
     * Сохранение конференц комнаты
     */
    public function saveAction(): void
    {
        if ( ! $this->request->isPost()) {
            return;
        }
        $this->db->begin();
        $data = $this->request->getPost();
        $room = ConferenceRooms::findFirstByUniqid($data['uniqid']);
        if ($room === null) {
            $room                         = new ConferenceRooms();
            $extension                    = new Extensions();
            $extension->type              = "CONFERENCE";
            $extension->number            = $data["extension"];
            $extension->callerid          = parent::transliterate($data["name"]);
            $extension->userid            = null;
            $extension->show_in_phonebook = 1;
            $extension->public_access     = 1;

        } else {
            $extension = $room->Extensions;
        }

        // Заполним параметры внутреннего номера
        if ( ! $this->updateExtension($extension, $data)) {
            $this->view->success = false;
            $this->db->rollback();

            return;
        }

        // Заполним параметры участников очереди
        if ( ! $this->updateConferenceRoom($room, $data)) {
            $this->view->success = false;
            $this->db->rollback();

            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();

        // Если это было создание карточки то надо перегрузить страницу с указанием ID
        if (empty($data['id'])) {
            $this->view->reload = "conference-rooms/modify/{$data['uniqid']}";
        }
    }

    /**
     * Обновление параметров внутреннего номера
     *
     * @param \MikoPBX\Common\Models\Extensions $extension
     * @param array                      $data массив полей из POST запроса
     *
     * @return bool update result
     */
    private function updateExtension(Extensions $extension, array $data): bool
    {

        $extension->number   = $data['extension'];
        $extension->callerid = parent::transliterate($data['name']);
        if ($extension->save() === false) {
            $errors = $extension->getMessages();
            $this->flash->error(implode('<br>', $errors));

            return false;
        }

        return true;

    }

    /**
     * Обновление параметров очереди
     *
     * @param \MikoPBX\Common\Models\ConferenceRooms $room
     * @param array                           $data массив полей из POST запроса
     *
     * @return bool update result
     */
    private function updateConferenceRoom(ConferenceRooms $room, array $data): bool
    {
        foreach ($room as $name => $value) {
            switch ($name) {
                case "extension":
                    $room->$name = $data[$name];
                    break;
                case "name":
                    $room->$name = $data[$name];
                    break;
                default:
                    if ( ! array_key_exists($name, $data)) {
                        continue 2;
                    }
                    $room->$name = $data[$name];
            }
        }
        if ($room->save() === false) {
            $errors = $room->getMessages();
            $this->flash->error(implode('<br>', $errors));

            return false;
        }

        return true;
    }

    /**
     * Удаление конференцкомнаты
     *
     * @param string|NULL $uniqid
     */
    public function deleteAction(string $uniqid = null)
    {
        $this->db->begin();
        $queue = ConferenceRooms::findFirstByUniqid($uniqid);

        $errors = false;
        if ($queue !== null && ! $queue->Extensions->delete()) {
            $errors = $queue->Extensions->getMessages();
        }

        if ($errors) {
            $this->flash->warning(implode('<br>', $errors));
            $this->db->rollback();
        } else {
            $this->db->commit();
        }

        return $this->forward('conference-rooms/index');
    }
}