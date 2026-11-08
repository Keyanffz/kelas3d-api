<?php

namespace Database\Seeders;

use App\Models\AppRule;
use Illuminate\Database\Seeder;

class AppRuleSeeder extends Seeder
{
    // process: nama proses sama persis, huruf besar-kecil diabaikan. title: judul window mengandung pattern.
    // Prioritas lebih besar menang, jadi ChatGPT yang dibuka di Chrome tetap terhitung vibe_coding.
    public function run(): void
    {
        $rules = [
            // match_on, pattern, os, activity, label, priority
            ['process', 'Code.exe', 'windows', 'ngoding', 'VS Code', 10],
            ['process', 'code', 'linux', 'ngoding', 'VS Code', 10],
            ['process', 'Code', 'macos', 'ngoding', 'VS Code', 10],
            ['process', 'pycharm64.exe', 'windows', 'ngoding', 'PyCharm', 10],
            ['process', 'pycharm', 'linux', 'ngoding', 'PyCharm', 10],
            ['process', 'pycharm', 'macos', 'ngoding', 'PyCharm', 10],
            ['title', 'Jupyter', null, 'ngoding', 'Jupyter', 20],

            ['title', 'ChatGPT', null, 'vibe_coding', 'ChatGPT', 30],
            ['title', 'Claude', null, 'vibe_coding', 'Claude', 30],
            ['title', 'Gemini', null, 'vibe_coding', 'Gemini', 30],

            ['process', 'EXCEL.EXE', 'windows', 'ngolah_data', 'Excel', 10],
            ['process', 'Microsoft Excel', 'macos', 'ngolah_data', 'Excel', 10],
            ['process', 'tableau.exe', 'windows', 'ngolah_data', 'Tableau', 10],
            ['process', 'Tableau', 'macos', 'ngolah_data', 'Tableau', 10],

            ['process', 'chrome.exe', 'windows', 'browsing', 'Chrome', 5],
            ['process', 'msedge.exe', 'windows', 'browsing', 'Edge', 5],
            ['process', 'firefox.exe', 'windows', 'browsing', 'Firefox', 5],
            ['process', 'chrome', 'linux', 'browsing', 'Chrome', 5],
            ['process', 'firefox', 'linux', 'browsing', 'Firefox', 5],
            ['process', 'Google Chrome', 'macos', 'browsing', 'Chrome', 5],
            ['process', 'Safari', 'macos', 'browsing', 'Safari', 5],
            ['process', 'Firefox', 'macos', 'browsing', 'Firefox', 5],

            ['process', 'steam.exe', 'windows', 'main_game', 'Steam', 10],
            ['process', 'EpicGamesLauncher.exe', 'windows', 'main_game', 'Epic Games', 10],
            ['process', 'RiotClientServices.exe', 'windows', 'main_game', 'Riot Client', 10],
            ['process', 'steam', 'linux', 'main_game', 'Steam', 10],
            ['process', 'steam_osx', 'macos', 'main_game', 'Steam', 10],
        ];

        foreach ($rules as [$matchOn, $pattern, $os, $activity, $label, $priority]) {
            AppRule::create([
                'match_on' => $matchOn,
                'pattern' => $pattern,
                'os' => $os,
                'activity' => $activity,
                'label' => $label,
                'priority' => $priority,
            ]);
        }
    }
}
