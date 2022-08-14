<?php

namespace App\Console\Commands;

use App\Models\Data;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ScrapePangan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:pangan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ids = $this->getIds();

        $bar = $this->output->createProgressBar(count($ids));
        
        foreach ($ids as $id) {
            $data = $this->getDetail($id);
            
            Data::query()->create($data);

            $bar->advance();
        }

        $bar->finish();
    }

    private function getIds()
    {
        $ids = [];

        $response = Http::get('http://panganku.org/id-ID/semua_nutrisi');

        (new Crawler($response->body()))
            ->filter('#data tbody tr')
            ->each(function (Crawler $tr) use (&$ids) {
                $ids[] = $tr->filter('td')->eq(1)->text();
            });
        
        return $ids;
    }

    private function getDetail(string $id)
    {
        $data = [];

        $response = Http::asForm()
            ->post('http://panganku.org/id-ID/view', [
                'haha' => $id
            ]);

        $crawler = new Crawler($response->body());

        $detail = $crawler->filter('.mantapinaja table')->first();
        
        $keys = [
            'kode',
            'nama',
            'nama_latin',
            'asal',
            'kelompok',
            'tipe',
            'deskripsi',
        ];

        $getValue = fn (int $index) => $detail->filter('tr')->eq($index)->filter('td')->eq(2)->text();

        foreach ($keys as $index => $key) {
            $value = $getValue($index);
            $data[$key] = $value === '' ? null : $value;
        }

        $gizi = [];

        $crawler->filterXPath("//table[contains(@id, 'showtkpi')]")
            ->each(function (Crawler $table) use (&$gizi, $id) {
                $table->filter('tr')->each(function (Crawler $tr) use (&$gizi, $id) {
                    $tds = $tr->filter('td');

                    if (!$tds->count()) {
                        return;
                    }

                    $key = $tds->eq(0);
                    $id = trim(explode('(<i>', $key->html())[0]);
                    $en = $key->filter('i')->text();
                    $value = trim($tds->eq(1)->text(), chr(0xC2).chr(0xA0)." \t\n\r\0\x0B:");

                    $gizi[] = [
                        'id' => $id,
                        'en' => $en,
                        'value' => $value
                    ];
                });
            });

        $data['gizi'] = $gizi;

        return $data;
    }
}
