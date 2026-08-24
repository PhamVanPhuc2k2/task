import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { taskKeys } from "@/features/tasks/api/tasks-api";
import { api, type ApiError } from "@/lib/api-client";
import type { Paginated, Wrapped } from "@/lib/pagination";

import type { Comment } from "../types/comment";

export const commentKeys = {
  ofTask: (taskId: string) => ["comments", taskId] as const,
};

export function useComments(
  taskId: string,
): UseQueryResult<Comment[], ApiError> {
  return useQuery({
    queryKey: commentKeys.ofTask(taskId),
    queryFn: async () =>
      (await api.get<Paginated<Comment>>(`/tasks/${taskId}/comments`)).data,
  });
}

export function useCreateComment(
  taskId: string,
): UseMutationResult<
  Comment,
  ApiError,
  { body: string; parent_id?: string | null }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (await api.post<Wrapped<Comment>>(`/tasks/${taskId}/comments`, input))
        .data,
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: commentKeys.ofTask(taskId),
      });
      // Số bình luận hiện trên thẻ task và trang chi tiết — làm mới luôn để
      // con số không lệch với danh sách ngay bên dưới nó.
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

export function useUpdateComment(
  taskId: string,
): UseMutationResult<Comment, ApiError, { id: string; body: string }> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, body }) =>
      (await api.patch<Wrapped<Comment>>(`/comments/${id}`, { body })).data,
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: commentKeys.ofTask(taskId),
      });
    },
  });
}

export function useDeleteComment(
  taskId: string,
): UseMutationResult<void, ApiError, string> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.delete<void>(`/comments/${id}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: commentKeys.ofTask(taskId),
      });
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

/**
 * Đính kèm tệp vào một bình luận đã tạo.
 *
 * Hai bước — tạo bình luận rồi mới tải tệp — chứ không gửi chung một request
 * multipart: bình luận không kèm tệp là phần lớn trường hợp, không nên bắt mọi
 * client dựng multipart cho chúng.
 */
export function useUploadAttachments(
  taskId: string,
): UseMutationResult<Comment, ApiError, { commentId: string; files: File[] }> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ commentId, files }) => {
      const form = new FormData();

      for (const file of files) {
        form.append("files[]", file);
      }

      return (
        await api.post<Wrapped<Comment>>(
          `/comments/${commentId}/attachments`,
          form,
        )
      ).data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: commentKeys.ofTask(taskId),
      });
    },
  });
}

export function useDeleteAttachment(
  taskId: string,
): UseMutationResult<void, ApiError, { commentId: string; mediaId: string }> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ commentId, mediaId }) =>
      api.delete<void>(`/comments/${commentId}/attachments/${mediaId}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: commentKeys.ofTask(taskId),
      });
    },
  });
}
