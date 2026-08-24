import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type {
  AttendanceDecisionValue,
  MyAttendance,
  TeamAttendance,
  WorkDayDetail,
} from "../types/attendance";
import type { DayTimeline } from "../types/timeline";

export const attendanceKeys = {
  all: ["attendance"] as const,
  me: (month: string) => ["attendance", "me", month] as const,
  team: (month: string, departmentId: string) =>
    ["attendance", "team", month, departmentId] as const,
  day: (userId: string, date: string) =>
    ["attendance", "day", userId, date] as const,
  timeline: (date: string) => ["attendance", "timeline", date] as const,
};

/**
 * Dòng thời gian một ngày của cả đội.
 *
 * `refetchInterval` 60 giây: đây là màn hình để mở suốt buổi sáng, và một dòng
 * thời gian đứng yên trong khi người ta đang làm việc thì nói sai. Nhịp tim
 * cũng đi mỗi phút nên nhanh hơn cũng không thêm dữ liệu gì.
 */
export function useDayTimeline(
  date: string,
  enabled: boolean,
): UseQueryResult<DayTimeline, ApiError> {
  return useQuery({
    queryKey: attendanceKeys.timeline(date),
    queryFn: async () =>
      (
        await api.get<Wrapped<DayTimeline>>("/attendance/timeline", {
          query: { date },
        })
      ).data,
    enabled,
    refetchInterval: 60_000,
    placeholderData: (previous) => previous,
  });
}

export function useMyAttendance(
  month: string,
): UseQueryResult<MyAttendance, ApiError> {
  return useQuery({
    queryKey: attendanceKeys.me(month),
    queryFn: async () =>
      (
        await api.get<Wrapped<MyAttendance>>("/attendance/me", {
          query: { month },
        })
      ).data,
  });
}

export function useTeamAttendance(
  month: string,
  departmentId: string,
  enabled: boolean,
): UseQueryResult<TeamAttendance, ApiError> {
  return useQuery({
    queryKey: attendanceKeys.team(month, departmentId),
    queryFn: async () =>
      (
        await api.get<Wrapped<TeamAttendance>>("/attendance/team", {
          query: { month, department_id: departmentId || undefined },
        })
      ).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

/** Chi tiết một ngày: các phiên và số lần đụng vào công việc. */
export function useWorkDay(
  userId: string,
  date: string,
  enabled: boolean,
): UseQueryResult<WorkDayDetail, ApiError> {
  return useQuery({
    queryKey: attendanceKeys.day(userId, date),
    queryFn: async () =>
      (await api.get<Wrapped<WorkDayDetail>>(`/attendance/${userId}/${date}`))
        .data,
    enabled,
  });
}

export interface ReviewInput {
  userId: string;
  work_date: string;
  decision: AttendanceDecisionValue;
  reason: string;
  adjusted_minutes?: number | null;
}

export function useReviewWorkDay(): UseMutationResult<
  unknown,
  ApiError,
  ReviewInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ userId, ...body }) =>
      api.post<unknown>(`/attendance/${userId}/review`, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}
