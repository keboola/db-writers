#!/usr/bin/env bash
set -e

VERBOSE=false

help () {
  echo "Syntax: ci-find-changes.sh [-v,--verbose] <targetBranch> <varName>:<path>"
  echo "Options:"
  echo "  -v|--verbose    Output extra information"
  echo ""
  echo "Example: ci-find-changes.sh main internalApi:apps/internal-api internalApiPhpClient:libs/internal-api-php-client"
  echo ""
}

POSITIONAL_ARGS=()
while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--verbose)
      VERBOSE=true
      shift
      ;;
    -h|--help)
      help
      exit 0
      ;;
    -*|--*)
      echo "Unknown option $1"
      echo ""
      help
      exit 1
      ;;
    *)
      POSITIONAL_ARGS+=("$1")
      shift
      ;;
  esac
done
set -- "${POSITIONAL_ARGS[@]}"

TARGET_BRANCH=${1:-}
if [[ $TARGET_BRANCH = "" ]]; then
    echo "Missing <targetBranch> argument"
    echo ""
    help
    exit 1
fi

ALL_CHANGES=
for PROJECT in ${@:2}; do
  PROJECT_CONFIG=(${PROJECT//:/ })
  PROJECT_VAR_NAME=${PROJECT_CONFIG[0]}
  PROJECT_DIR=${PROJECT_CONFIG[1]}

  DIR_EXISTS_IN_TARGET_BRANCH=$(git ls-tree -d "origin/${TARGET_BRANCH}:${PROJECT_DIR}" >/dev/null 2>&1 && echo 1 || echo 0)
  if [[ $DIR_EXISTS_IN_TARGET_BRANCH -eq 0 ]]; then
    HAS_CHANGES=1
  else
    PROJECT_CHANGES=$(git diff --name-only "origin/${TARGET_BRANCH}" "${PROJECT_DIR}")

    if [[ $(echo -n "${PROJECT_CHANGES}" | xargs | nl -bt - | wc -l) -gt 0 ]]; then
      HAS_CHANGES=1

      if [ "${VERBOSE}" = true ]; then
        echo "${PROJECT_CHANGES}"
        echo ""
      fi
    else
      HAS_CHANGES=0
    fi
  fi

  if [[ $HAS_CHANGES -eq 1 ]]; then
    echo "echo \"changedProjects_${PROJECT_VAR_NAME}=1\" >> \$GITHUB_OUTPUT"
    ALL_CHANGES="${ALL_CHANGES} \"${PROJECT_VAR_NAME}\""
  fi
done

if [[ "${ALL_CHANGES}" == "" ]]; then
  for PROJECT in $@; do
    PROJECT_CONFIG=(${PROJECT//:/ })
    PROJECT_VAR_NAME=${PROJECT_CONFIG[0]}

    echo "echo \"changedProjects_${PROJECT_VAR_NAME}=1\" >> \$GITHUB_OUTPUT"
    ALL_CHANGES="${ALL_CHANGES} \"${PROJECT_VAR_NAME}\""
  done
fi