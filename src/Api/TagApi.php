<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 标签接口
 * @see document/html/tag.htm
 */
class TagApi extends BaseApi
{
    /**
     * 获取标签数据
     * @param string $tagname 标签名称
     * @param array $nums 应用 ID => 返回条数
     */
    public function getTag(string $tagname, array $nums = []): array
    {
        $params = ['tagname' => $tagname];
        if (!empty($nums)) {
            $params['nums'] = $nums;
        }
        $ret = $this->client->request('tag/gettag', $params);
        return is_array($ret) ? $ret : [];
    }
}
