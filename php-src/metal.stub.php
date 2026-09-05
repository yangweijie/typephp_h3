<?php

function h3_metal_device_create(): int {}
function h3_metal_device_get_info(int $device): array {}
function h3_metal_device_get_name(int $device): string {}
function h3_metal_device_supports_metal4(int $device): bool {}
function h3_metal_device_free(int $device): void {}

function h3_metal_buffer_create(int $device, int $length, int $options): int {}
function h3_metal_buffer_get_length(int $buffer): int {}
function h3_metal_buffer_get_contents(int $buffer, int $offset, int $length): string {}
function h3_metal_buffer_set_contents(int $buffer, string $data, int $offset): void {}
function h3_metal_buffer_get_gpu_address(int $buffer): int {}
function h3_metal_buffer_free(int $buffer): void {}

function h3_metal_command_queue_create(int $device): int {}
function h3_metal_command_buffer_create(int $queue): int {}
function h3_metal_compute_encoder_create(int $cmdBuffer): int {}
function h3_metal_compute_encoder_set_pipeline(int $encoder, int $pipeline): void {}
function h3_metal_compute_encoder_set_buffer(int $encoder, int $buffer, int $index, int $offset): void {}
function h3_metal_compute_encoder_set_bytes(int $encoder, string $data, int $index): void {}
function h3_metal_compute_encoder_dispatch(int $encoder, int $gridX, int $gridY, int $gridZ, int $tgX, int $tgY, int $tgZ): void {}
function h3_metal_compute_encoder_end(int $encoder): void {}
function h3_metal_command_buffer_commit(int $buffer): void {}
function h3_metal_command_buffer_wait(int $buffer): void {}
function h3_metal_command_buffer_free(int $buffer): void {}
function h3_metal_command_queue_free(int $queue): void {}

function h3_metal_pipeline_create(int $device, string $shaderSource, string $functionName): int {}
function h3_metal_pipeline_create_with_file(int $device, string $metallibPath, string $functionName): int {}
function h3_metal_pipeline_get_max_threads_per_threadgroup(int $pipeline): int {}
function h3_metal_pipeline_free(int $pipeline): void {}
