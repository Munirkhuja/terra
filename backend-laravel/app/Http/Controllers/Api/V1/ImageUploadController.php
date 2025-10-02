<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventEnum;
use App\Enums\SourceEnum;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadStoreRequest;
use App\Http\Resources\ImageUploadResource;
use App\Models\ImageUpload;
use App\QueryFilters\CreatedAt;
use App\QueryFilters\CursorPaginateLoc;
use App\QueryFilters\Description;
use App\QueryFilters\EqualKeyID;
use App\QueryFilters\Event;
use App\QueryFilters\Sort;
use App\QueryFilters\Status;
use App\QueryFilters\Title;
use App\Traits\JsonArrayTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pipeline\Pipeline;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Images",
 *     description="Управление изображениями"
 * )
 */
class ImageUploadController extends Controller
{
    use JsonArrayTrait;

    /**
     * @OA\Get(
     *     path="/api/image-upload",
     *     tags={"Images"},
     *     summary="Список изображений",
     *     security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="title",
     *          in="query",
     *          required=false,
     *          description="Фильтр по заголовку",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="description",
     *          in="query",
     *          required=false,
     *          description="Фильтр по описанию",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          in="query",
     *          required=false,
     *          description="Статус элемента",
     *          @OA\Schema(
     *              type="string",
     *              enum={"processing","success","failed"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="event",
     *          in="query",
     *          required=false,
     *          description="Фильтр по событию",
     *           @OA\Schema(
     *               type="string",
     *               enum={"get_coordinate"}
     *           )
     *      ),
     *      @OA\Parameter(
     *          name="created_at",
     *          in="query",
     *          required=false,
     *          description="Фильтр по точной дате создания",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="created_at_from",
     *          in="query",
     *          required=false,
     *          description="Начало периода фильтрации по дате создания",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="created_at_to",
     *          in="query",
     *          required=false,
     *          description="Конец периода фильтрации по дате создания",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="sort",
     *          in="query",
     *          required=false,
     *          description="Сортировка по полю, например: 'created_at' или '-created_at' для DESC",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          required=false,
     *          description="Количество элементов на страницу",
     *          @OA\Schema(type="integer", example=50)
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Список изображений",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="image_uploads", type="array",
     *                 @OA\Items(ref="#/components/schemas/ImageUploadResource")
     *             ),
     *             @OA\Property(property="status_image", type="object",
     *                 example={"pending": "В обработке"}
     *             ),
     *             @OA\Property(property="event_image", type="object",
     *                 example={"get_coordinate": "Получить координаты"}
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $image_uploads = app(Pipeline::class)
            ->send(ImageUpload::query()->where('user_id', auth()->id()))
            ->through(
                [
                    EqualKeyID::class,
                    Title::class,
                    Description::class,
                    Status::class,
                    Event::class,
                    CreatedAt::class,
                    Sort::class,
                    CursorPaginateLoc::class,
                ]
            )->thenReturn();

        return response()->json([
            'image_uploads' => new ImageUpload($image_uploads),
            'status_image' => StatusEnum::labels(),
            'event_image' => EventEnum::labels(),
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/image-upload",
     *     tags={"Images"},
     *     summary="Загрузка изображения",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                  required={"title"},
     *                  @OA\Property(
     *                      property="title",
     *                      description="Название изображения",
     *                      type="string",
     *                      maxLength=255,
     *                      example="My Photo"
     *                  ),
     *                  @OA\Property(
     *                      property="description",
     *                      description="Описание изображения",
     *                      type="string",
     *                      example="Фото с мероприятия"
     *                  ),
     *                  @OA\Property(
     *                      property="image",
     *                      description="Файл изображения",
     *                      type="string",
     *                      format="file"
     *                  ),
     *                   @OA\Property(
     *                       property="image_base64",
     *                       description="Файл изображения",
     *                       type="string"
     *                   ),
     *                  @OA\Property(
     *                      property="metadata",
     *                      description="Дополнительные данные",
     *                      type="string",
     *                      example={"author":"John"}
     *                  ),
     *                  @OA\Property(
     *                      property="event",
     *                      description="Событие изображения",
     *                      type="string",
     *                      enum={"get_coordinate"}
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Изображение загружено",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="image_upload",
     *                 ref="#/components/schemas/ImageUploadResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Похожее изображение уже создано недавно и находится в статусе обработки",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Похожее изображение уже создано недавно и находится в статусе обработки"
     *             ),
     *             @OA\Property(
     *                 property="image_upload",
     *                 ref="#/components/schemas/ImageUploadResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"title": {"The title field is required."}}
     *             )
     *         )
     *     )
     * )
     */


    public function store(ImageUploadStoreRequest $request): JsonResponse
    {
        $data = [
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'event' => $request->event ?? EventEnum::GET_COORDINATE->value,
            'status' => StatusEnum::PROCESSING->value,
            'source' => SourceEnum::API->value,
        ];
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);
        // Проверяем дубликат
        $duplicate = ImageUpload::query()
            ->where($data)
            ->where('created_at', '>=', $fiveMinutesAgo)
            ->first();
        if ($duplicate) {
            return response()->json([
                'message' => 'Похожее изображение уже создано недавно и находится в статусе обработки',
                'image_upload' => $duplicate
            ], 409); // 409 Conflict
        }
        $data['metadata'] = $this->toArrayWithTrait($request->metadata);
        // Создаём модель
        $image_upload = ImageUpload::create($data);
        // === Обработка фото ===
        if ($request->hasFile('image')) {
            // Обычный файл
            $image_upload->addMediaFromRequest('image')->toMediaCollection('images');
        } elseif ($base64 = $request->image_base64) {
            // Base64 файл
            $image_upload->addMediaFromBase64($base64)->toMediaCollection('images');
        }

        return response()->json([
            'image_upload' => new ImageUploadResource($image_upload)
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/image-upload/{id}",
     *     tags={"Images"},
     *     summary="Просмотр одного изображения",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID файла", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Изображение найдено",
     *         @OA\JsonContent(
     *               type="object",
     *               @OA\Property(property="image_upload", type="object", ref="#/components/schemas/ImageUploadResource")
     *          )
     *     ),
     *     @OA\Response(response=404, description="Не найдено")
     * )
     */
    public function show($id): JsonResponse
    {
        $image_upload = ImageUpload::query()->findOrFail($id);

        return response()->json([
            'image_upload' => new ImageUploadResource($image_upload)
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/image-upload/{name}",
     *     tags={"Images"},
     *     summary="Удаление изображения",
     *     security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, description="ID файла", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Изображение удалено"),
     *     @OA\Response(response=404, description="Не найдено")
     * )
     */
    public function destroy($id): JsonResponse
    {
        ImageUpload::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Удалено']);
    }
}
