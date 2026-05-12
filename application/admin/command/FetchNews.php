<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

/**
 * 新闻采集命令 - 直接爬取新闻网站
 * 用法: php think fetch_news
 */
class FetchNews extends Command
{
    // 新闻源配置
    private $sources = [
        1 => [ // 综合资讯
            'name' => '综合资讯',
            'groups' => [
                'crypto' => [
                    'name' => '虚拟货币',
                    'url' => 'https://finance.sina.com.cn/blockchain/',
                    'limit' => 3
                ],
                'forex' => [
                    'name' => '外汇',
                    'url' => 'https://finance.sina.com.cn/forex/',
                    'limit' => 3
                ],
                'metal' => [
                    'name' => '贵金属',
                    'url' => 'https://finance.sina.com.cn/futuremarket/',
                    'limit' => 4
                ]
            ]
        ],
        2 => [ // 学院教程（也采集金融新闻）
            'name' => '学院教程',
            'groups' => [
                'crypto2' => [
                    'name' => '虚拟货币',
                    'url' => 'https://finance.sina.com.cn/blockchain/',
                    'limit' => 3
                ],
                'forex2' => [
                    'name' => '外汇',
                    'url' => 'https://finance.sina.com.cn/forex/',
                    'limit' => 3
                ],
                'metal2' => [
                    'name' => '贵金属',
                    'url' => 'https://finance.sina.com.cn/futuremarket/',
                    'limit' => 4
                ]
            ]
        ]
    ];

    protected function configure()
    {
        $this->setName('fetch_news')
            ->setDescription('从新闻网站采集金融新闻');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始采集新闻...');

        $stats = ['新增' => 0, '跳过' => 0, '失败' => 0];

        foreach ($this->sources as $categoryId => $category) {
            $output->writeln("\n正在采集分类: {$category['name']} (目标: 10条)");
            
            $totalCollected = 0;
            
            // 两个分类都按分组采集
            foreach ($category['groups'] as $groupKey => $group) {
                $output->writeln("\n  [{$group['name']}] 目标: {$group['limit']}条");
                
                $newsList = $this->fetchFromSina($group['url'], $group['limit']);
                
                $collected = 0;
                foreach ($newsList as $news) {
                    if ($collected >= $group['limit']) {
                        break;
                    }
                    
                    $result = $this->saveNews($news, $categoryId, $output, $stats);
                    if ($result === 'inserted') {
                        $collected++;
                    }
                }
                
                $output->writeln("  [{$group['name']}] 完成: {$collected}/{$group['limit']}条");
                $totalCollected += $collected;
                
                sleep(2); // 避免请求过快
            }
            
            $output->writeln("\n分类 {$category['name']} 采集完成: {$totalCollected}/10 条");
        }

        $output->writeln("\n采集完成: 新增={$stats['新增']}, 跳过={$stats['跳过']}, 失败={$stats['失败']}");
    }

    /**
     * 从新浪财经采集新闻
     */
    private function fetchFromSina($url, $limit = 10)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($html)) {
                return [];
            }

            // 解析HTML，提取新闻列表
            $newsList = [];
            
            // 匹配新闻链接和标题
            preg_match_all('/<a[^>]+href="(https?:\/\/[^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                if (count($newsList) >= $limit * 3) { // 多采集一些，后面过滤
                    break;
                }
                
                $newsUrl = $match[1];
                $title = trim(strip_tags($match[2]));
                
                // 过滤：必须是新闻详情页
                if (strpos($newsUrl, 'finance.sina.com.cn') === false) {
                    continue;
                }
                
                if (strpos($newsUrl, '.shtml') === false && strpos($newsUrl, '.html') === false) {
                    continue;
                }
                
                // 过滤：标题长度合理
                if (mb_strlen($title, 'UTF-8') < 10 || mb_strlen($title, 'UTF-8') > 100) {
                    continue;
                }
                
                // 过滤：标题包含中文
                if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $title)) {
                    continue;
                }
                
                $newsList[] = [
                    'title' => $title,
                    'url' => $newsUrl
                ];
            }
            
            return array_slice($newsList, 0, $limit * 2); // 返回2倍数量，确保有足够的新闻
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取新闻详情
     */
    private function fetchNewsDetail($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($html)) {
                return null;
            }

            // 转换编码
            if (mb_detect_encoding($html, 'UTF-8', true) === false) {
                $html = mb_convert_encoding($html, 'UTF-8', 'GBK');
            }

            // 提取正文内容 - 使用DOMDocument更可靠
            $content = '';
            $cover = '';
            
            // 尝试多种方式提取内容
            // 方式1: id="artibody"
            if (preg_match('/<div[^>]+id=["\']artibody["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $matches)) {
                $content = $matches[1];
            }
            // 方式2: class="article"
            elseif (preg_match('/<div[^>]+class=["\'][^"\']*article[^"\']*["\'][^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $matches)) {
                $content = $matches[1];
            }
            // 方式3: 查找所有p标签集合
            else {
                preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $pMatches);
                if (!empty($pMatches[1])) {
                    // 过滤掉太短的段落
                    $paragraphs = array_filter($pMatches[1], function($p) {
                        $text = strip_tags($p);
                        return mb_strlen($text, 'UTF-8') > 20;
                    });
                    
                    if (count($paragraphs) >= 3) {
                        $content = '<p>' . implode('</p><p>', $paragraphs) . '</p>';
                    }
                }
            }
            
            // 提取第一张图片作为封面
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatch)) {
                $imgUrl = $imgMatch[1];
                // 确保是完整URL
                if (strpos($imgUrl, 'http') === 0) {
                    $cover = $imgUrl;
                }
            }
            
            // 清理内容
            if (!empty($content)) {
                // 移除script、style、注释
                $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
                $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
                $content = preg_replace('/<!--.*?-->/s', '', $content);
                
                // 移除图片标签
                $content = preg_replace('/<img[^>]*>/i', '', $content);
                
                // 移除内联样式和class
                $content = preg_replace('/\s+style=["\'][^"\']*["\']/i', '', $content);
                $content = preg_replace('/\s+class=["\'][^"\']*["\']/i', '', $content);
                
                // 只保留p、br、strong、em标签（不要img）
                $content = strip_tags($content, '<p><br><strong><em>');
                
                // 移除广告文字和常见无用信息
                $adTexts = [
                    '海量资讯、精准解读，尽在新浪财经APP',
                    '新浪财经APP',
                    '新浪财经',
                    '责任编辑',
                    '扫二维码',
                    '关注公众号',
                    '更多精彩内容',
                    '点击进入',
                    '查看更多',
                    '声明：',
                    '免责声明',
                    '风险提示',
                    '本文不构成投资建议',
                    '市场有风险',
                    '投资需谨慎',
                    '仅供参考',
                    '转载请注明',
                    '版权所有',
                    '未经许可',
                    '禁止转载',
                ];
                
                foreach ($adTexts as $adText) {
                    $content = str_replace($adText, '', $content);
                }
                
                // 移除包含特定关键词的段落
                $removePatterns = [
                    '/<p>[^<]*海量资讯[^<]*<\/p>/iu',
                    '/<p>[^<]*新浪财经[^<]*<\/p>/iu',
                    '/<p>[^<]*责任编辑[^<]*<\/p>/iu',
                    '/<p>[^<]*扫二维码[^<]*<\/p>/iu',
                    '/<p>[^<]*关注[^<]*公众号[^<]*<\/p>/iu',
                    '/<p>[^<]*声明[：:][^<]*<\/p>/iu',
                    '/<p>[^<]*免责[^<]*<\/p>/iu',
                    '/<p>[^<]*风险提示[^<]*<\/p>/iu',
                    '/<p>[^<]*投资建议[^<]*<\/p>/iu',
                    '/<p>[^<]*市场有风险[^<]*<\/p>/iu',
                    '/<p>[^<]*转载请注明[^<]*<\/p>/iu',
                    '/<p>[^<]*版权所有[^<]*<\/p>/iu',
                ];
                
                foreach ($removePatterns as $pattern) {
                    $content = preg_replace($pattern, '', $content);
                }
                
                // 移除来源、作者、编辑等元信息（括号内）
                $metaPatterns = [
                    '/[（(]来源[：:][^）)]*[）)]/u',
                    '/[（(]作者[：:][^）)]*[）)]/u',
                    '/[（(]编辑[：:][^）)]*[）)]/u',
                    '/[（(]记者[：:][^）)]*[）)]/u',
                    '/[（(]撰稿[：:][^）)]*[）)]/u',
                    '/[（(]文[：:][^）)]*[）)]/u',
                    '/[（(]图[：:][^）)]*[）)]/u',
                ];
                
                foreach ($metaPatterns as $pattern) {
                    $content = preg_replace($pattern, '', $content);
                }
                
                // 移除包含元信息的整个段落
                $content = preg_replace('/<p>[^<]*[（(]来源[：:][^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>[^<]*[（(]作者[：:][^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>[^<]*[（(]编辑[：:][^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>[^<]*[（(]记者[：:][^<]*<\/p>/iu', '', $content);
                
                // 移除"本文来自"、"原标题"等开头的段落
                $content = preg_replace('/<p>\s*本文来自[^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>\s*原标题[：:][^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>\s*文章来源[：:][^<]*<\/p>/iu', '', $content);
                $content = preg_replace('/<p>\s*专题[：:][^<]*<\/p>/iu', '', $content);
                
                // 移除专题信息（段落内）
                $content = preg_replace('/专题[：:][^\n<]*/', '', $content);
                
                // 移除空段落
                $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
                $content = preg_replace('/<p>(&nbsp;|\s)*<\/p>/i', '', $content);
                
                // 确保每个段落都有p标签
                if (strpos($content, '<p>') === false) {
                    $lines = explode("\n", strip_tags($content));
                    $formatted = '';
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line) && mb_strlen($line, 'UTF-8') > 10) {
                            $formatted .= "<p>{$line}</p>\n";
                        }
                    }
                    $content = $formatted;
                }
                
                $content = trim($content);
            }
            
            return [
                'content' => $content,
                'cover' => $cover
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 保存新闻
     */
    private function saveNews($news, $categoryId, $output, &$stats)
    {
        $title = $news['title'];
        $url = $news['url'];
        
        // 检查是否已存在
        $exists = Db::name('news')
            ->where('title', $title)
            ->find();

        if ($exists) {
            $output->writeln("    - 跳过(已存在): {$title}");
            $stats['跳过']++;
            return 'skipped';
        }

        // 获取详情
        $output->writeln("    正在获取: {$title}");
        $detail = $this->fetchNewsDetail($url);
        
        if (!$detail || empty($detail['content'])) {
            $output->writeln("    ✗ 失败(无法获取内容): {$title}");
            $stats['失败']++;
            return 'failed';
        }
        
        // 生成摘要（取前200字）
        $summary = mb_substr(strip_tags($detail['content']), 0, 200, 'UTF-8');
        
        // 插入数据库
        try {
            Db::name('news')->insert([
                'category_id' => $categoryId,
                'title' => $title,
                'cover' => $detail['cover'] ?: '/uploads/20260305/7f07cfe1e90a408bd53a69c6c27557c0.jpg',
                'summary' => $summary,
                'content' => $detail['content'],
                'status' => 1,
                'publish_time' => time(),
                'createtime' => time(),
                'updatetime' => time()
            ]);
            $output->writeln("    ✓ 新增: {$title}");
            $stats['新增']++;
            return 'inserted';
        } catch (\Exception $e) {
            $output->writeln("    ✗ 失败: {$title} - " . $e->getMessage());
            $stats['失败']++;
            return 'failed';
        }
    }
}
