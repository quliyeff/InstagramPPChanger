<?php

/*
 *  Copyright (C) 2019 Anıl Mısırlıoğlu
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Artemis;

use Artemis\entities\Settings;
use Artemis\utils\Config;
use Artemis\utils\ImageCompress;
use Artemis\utils\Internet;
use Artemis\utils\Signal;
use Artemis\utils\Terminal;
use Exception;
use InstagramAPI\Instagram;

class Main{

    use Internet;

    /** @var Instagram */
    private $instagram;
    /** @var Settings */
    private $settings;
    /** @var ImageCompress */
    private $imageCompress;

    public function __construct(){
        Terminal::log(Terminal::GOLD . 'Uygulama başlatılıyor...', SYSTEM);

        $this->settings = new Settings;
        $this->imageCompress = new ImageCompress;
        $this->startInstagram();

        $this->startApp();
    }

    private function startInstagram() : void{
        Terminal::log(Terminal::GREEN . 'Instagram başlatılıyor...', SYSTEM);

        Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;
        $this->instagram = new Instagram;

        Terminal::log(Terminal::GREEN . 'Instagram başarıyla başlatıldı.', SYSTEM);

        $this->loginInstagramAccount();
    }

    private function loginInstagramAccount() : void{
        $user = $this->settings->getUser();

        try{
            Terminal::log(Terminal::YELLOW . 'Instagram hesabınıza giriş yapılıyor.', SYSTEM);

            $response = $this->instagram->login(
                ($username = $user->getUsername()),
                ($password = $user->getPassword()));

            if($response !== null and $response->isTwoFactorRequired()){
                Terminal::log(Terminal::YELLOW . 'Hesabınızın iki adımlı doğrulaması açık. Lütfen size gelen doğrulama kodunu giriniz...', API);
                $twoFactorIdentifier = $response->getTwoFactorInfo()->getTwoFactorIdentifier();
                $verificationCode = trim(fgets(STDIN));
                $this->instagram->finishTwoFactorLogin($username, $password, $twoFactorIdentifier, $verificationCode);
            }

            Terminal::log(Terminal::GOLD . $username . Terminal::GREEN . ' hesabına başarıyla giriş yapıldı.', API);
        }catch(Exception $exception){
            Terminal::log(Terminal::RED . 'Bir sebepten dolayı hesabınıza girşi yapamadık. Hata: ' . Terminal::WHITE . $exception->getMessage(), API);
            exit(1);
        }

    }

    private function startApp() : void{
        sleep(60 - (time() % 60));

        while(true){
            Terminal::log(Terminal::LIGHT_PURPLE . 'Sistem tekrardan aktif.', SYSTEM);

            $this->downloadRandomImage();

            $dir = __DIR__ . '/assets/images/image.%s';
            $jpg = sprintf($dir, 'jpg');

            Terminal::log(Terminal::GREEN . 'Görüntü jpg formatından png formatına çevriliyor...', IMAGE_PROCESSOR);

            $this->imageCompress->jpgConvertToPng($jpg);

            Terminal::log(Terminal::GREEN . 'Görüntü jpg formatından png formatına başarıyla çevrildi.', IMAGE_PROCESSOR);
            unlink($jpg);

            Terminal::log(Terminal::RED . 'Eski görüntü verisi silindi.', SYSTEM);

            $png = sprintf($dir, 'png');

            Terminal::log(Terminal::GREEN . 'Görüntü yeniden boyutlandırıp, yeniden düzenleniyor...', IMAGE_PROCESSOR);

            $this->imageCompress
                ->resizeImage($png)
                ->drawCircleOnImage($png)
                ->writeOnImage($png, date('H:i'))
                ->writeOnImage($png, PHP_EOL . date('d.m.Y'));

            Terminal::log(Terminal::GREEN . 'Görüntü yeniden boyutlandırıldı ve düzenlendi. Görüntü verisi kaydedildi.', IMAGE_PROCESSOR);

            try{
                $this->instagram->account->changeProfilePicture($png);

                Terminal::log(Terminal::GREEN . 'Profil fotoğrafınız güncellendi.', API);
            }catch(Exception $exception){
                Terminal::log(Terminal::RED . 'Profil fotoğrafı bir sebepten dolayı değiştirilemedi. Program kapatılıyor. Hata: ' . Terminal::WHITE . $exception->getMessage(), API);
            }

            $sleep = 60 - (time() % 60);
            Terminal::log(Terminal::GOLD . 'Bir dahaki güncelleme için ' . Terminal::AQUA . $sleep . Terminal::GOLD . ' saniye bekleniyor...');

            usleep(($sleep * 1000000) - 500000);
        }

    }

}