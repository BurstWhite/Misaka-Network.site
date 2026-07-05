<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeSave;
use App\Http\Requests\Admin\KnowledgeSort;
use App\Models\Knowledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class KnowledgeController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $knowledge = Knowledge::find($request->input('id'))->toArray();
            if (!$knowledge)
                return $this->fail([400202, '知识不存在']);
            return $this->success($knowledge);
        }
        $data = Knowledge::select(['title', 'id', 'updated_at', 'category', 'show'])
            ->orderBy('sort', 'ASC')
            ->get();
        return $this->success($data);
    }

    public function getCategory(Request $request)
    {
        return $this->success(array_keys(Knowledge::get()->groupBy('category')->toArray()));
    }

    public function save(KnowledgeSave $request)
    {
        $params = $request->validated();

        if (!$request->input('id')) {
            if (!Knowledge::create($params)) {
                return $this->fail([500, '创建失败']);
            }
        } else {
            try {
                Knowledge::find($request->input('id'))->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, '创建失败']);
            }
        }

        return $this->success(true);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpg,jpeg,png,gif,webp|max:8192',
        ], [
            'image.required' => '请选择要上传的图片',
            'image.image' => '上传文件必须是图片',
            'image.mimes' => '图片格式仅支持 JPG、PNG、GIF、WEBP',
            'image.max' => '图片大小不能超过 8MB',
        ]);

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $datePath = now()->format('Y/m');
        $uploadRoot = base_path(".docker/.data/uploads/knowledge/{$datePath}");

        File::ensureDirectoryExists($uploadRoot, 0755, true);

        $filename = Str::uuid()->toString() . ".{$extension}";
        $file->move($uploadRoot, $filename);

        $relativePath = "uploads/knowledge/{$datePath}/{$filename}";
        $url = "/{$relativePath}";

        return $this->success([
            'url' => $url,
            'markdown' => "![图片]({$url})",
        ]);
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '知识库ID不能为空'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            throw new ApiException('知识不存在');
        }
        $knowledge->show = !$knowledge->show;
        if (!$knowledge->save()) {
            throw new ApiException('保存失败');
        }

        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ], [
            'ids.required' => '参数有误',
            'ids.array' => '参数有误'
        ]);
        try {
            DB::beginTransaction();
            foreach ($request->input('ids') as $k => $v) {
                $knowledge = Knowledge::find($v);
                $knowledge->timestamps = false;
                $knowledge->update(['sort' => $k + 1]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException('保存失败');
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '知识库ID不能为空'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            return $this->fail([400202, '知识不存在']);
        }
        if (!$knowledge->delete()) {
            return $this->fail([500, '删除失败']);
        }

        return $this->success(true);
    }
}
