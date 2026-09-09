#!/usr/bin/env python3
"""
H3PHP — ModelScope Download Bridge.

A Python bridge script for downloading models from ModelScope.
Called by PHP ModelScopeDownloader to leverage the official ModelScope SDK.

Usage:
    python modelscope_bridge.py download <model_id> <local_dir> [--cache-dir CACHE]
    python modelscope_bridge.py info <model_id>
    python modelscope_bridge.py check

Commands:
    download  Download a full model repo with resume support
    info      Get repo metadata (files, sizes) as JSON
    check     Check available download methods (CLI, SDK, git-lfs)
"""

import sys
import json
import os


def cmd_check():
    """Check which download methods are available."""
    result = {
        'modelscope_cli': False,
        'modelscope_sdk': False,
        'modelscope_version': None,
        'git_lfs': False,
        'git_lfs_version': None,
        'huggingface_hub': False,
    }

    # Check ModelScope SDK
    try:
        import modelscope
        result['modelscope_sdk'] = True
        result['modelscope_version'] = getattr(modelscope, '__version__', 'unknown')
    except ImportError:
        pass

    # Check HuggingFace Hub (used for repo info)
    try:
        import huggingface_hub
        result['huggingface_hub'] = True
    except ImportError:
        pass

    # Check ModelScope CLI
    import shutil
    result['modelscope_cli'] = shutil.which('modelscope') is not None

    # Check Git LFS
    import subprocess
    try:
        proc = subprocess.run(
            ['git', 'lfs', 'version'],
            capture_output=True, text=True, timeout=10
        )
        if proc.returncode == 0:
            result['git_lfs'] = True
            result['git_lfs_version'] = proc.stdout.strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass

    print(json.dumps(result, indent=2))
    return 0


def cmd_info(model_id: str):
    """Get model repository information as JSON."""
    try:
        from huggingface_hub import HfApi

        api = HfApi()
        info = api.repo_info(model_id, repo_type='model')

        files = []
        total_size = 0
        for f in (info.siblings or []):
            size = f.size or 0
            total_size += size
            files.append({
                'name': f.rfilename,
                'size': size,
            })

        result = {
            'model_id': model_id,
            'files': files,
            'total_size': total_size,
            'total_size_human': _format_size(total_size),
        }
    except Exception as e:
        result = {'error': str(e)}

    print(json.dumps(result, indent=2))
    return 0 if 'error' not in result else 1


def cmd_download(model_id: str, local_dir: str, cache_dir: str | None = None):
    """Download a model from ModelScope with progress reporting."""
    try:
        from modelscope import snapshot_download
    except ImportError:
        print(json.dumps({'error': 'modelscope package not installed. Run: pip install modelscope'}), file=sys.stderr)
        return 1

    # Ensure target directory exists
    os.makedirs(local_dir, exist_ok=True)

    kwargs = {
        'model_id': model_id,
        'local_dir': local_dir,
    }
    if cache_dir:
        kwargs['cache_dir'] = cache_dir

    try:
        # Download with progress
        print(json.dumps({'status': 'starting', 'model_id': model_id, 'local_dir': local_dir}))
        sys.stdout.flush()

        downloaded_path = snapshot_download(**kwargs)

        print(json.dumps({
            'status': 'completed',
            'path': downloaded_path,
        }))
        return 0
    except Exception as e:
        print(json.dumps({'status': 'failed', 'error': str(e)}), file=sys.stderr)
        return 1


def _format_size(size_bytes: int) -> str:
    """Format byte size to human-readable string."""
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if size_bytes < 1024:
            return f'{size_bytes:.1f} {unit}'
        size_bytes /= 1024
    return f'{size_bytes:.1f} PB'


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'Usage: modelscope_bridge.py <download|info|check> [args...]'}))
        return 1

    command = sys.argv[1]

    if command == 'check':
        return cmd_check()

    elif command == 'info':
        if len(sys.argv) < 3:
            print(json.dumps({'error': 'Usage: modelscope_bridge.py info <model_id>'}))
            return 1
        return cmd_info(sys.argv[2])

    elif command == 'download':
        if len(sys.argv) < 4:
            print(json.dumps({'error': 'Usage: modelscope_bridge.py download <model_id> <local_dir> [--cache-dir CACHE]'}))
            return 1

        model_id = sys.argv[2]
        local_dir = sys.argv[3]
        cache_dir = None

        # Parse optional --cache-dir
        i = 4
        while i < len(sys.argv):
            if sys.argv[i] == '--cache-dir' and i + 1 < len(sys.argv):
                cache_dir = sys.argv[i + 1]
                i += 2
            else:
                i += 1

        return cmd_download(model_id, local_dir, cache_dir)

    else:
        print(json.dumps({'error': f'Unknown command: {command}'}))
        return 1


if __name__ == '__main__':
    sys.exit(main())
