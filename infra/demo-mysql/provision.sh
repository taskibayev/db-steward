#!/bin/sh
set -eu

northwind_revision=0036c3ca2f2a010b77c7880a3b4badf85dbcfc7a
northwind_schema_sha=04db6a33deb6b0ca914abee4e67388f3144caca2f08eedb67b5f57f47d0cdfe6
northwind_data_sha=4c6cd29da9db41be7302defb9a8460ac0c5095f35adb3e1979ee60c4c3074c2e
sakila_sha=e27ed871082e76e9d32021c2df07f97ebf7696ace132f5995218557dd87d7046
demo_tmp_dir="$(mktemp -d)"

cleanup() {
    rm -rf "${demo_tmp_dir}"
}
trap cleanup EXIT

curl --fail --location --silent --show-error \
    --output "${demo_tmp_dir}/northwind-schema.sql" \
    "https://raw.githubusercontent.com/dalers/mywind/${northwind_revision}/northwind.sql"
curl --fail --location --silent --show-error \
    --output "${demo_tmp_dir}/northwind-data.sql" \
    "https://raw.githubusercontent.com/dalers/mywind/${northwind_revision}/northwind-data.sql"
curl --fail --location --silent --show-error \
    --output "${demo_tmp_dir}/sakila.zip" \
    "https://downloads.mysql.com/docs/sakila-db.zip"

printf '%s  %s\n' "${northwind_schema_sha}" "${demo_tmp_dir}/northwind-schema.sql" | sha256sum --check --status
printf '%s  %s\n' "${northwind_data_sha}" "${demo_tmp_dir}/northwind-data.sql" | sha256sum --check --status
printf '%s  %s\n' "${sakila_sha}" "${demo_tmp_dir}/sakila.zip" | sha256sum --check --status
unzip -q "${demo_tmp_dir}/sakila.zip" -d "${demo_tmp_dir}"

docker compose up -d client-mysql-demo
until docker compose exec -T client-mysql-demo mysqladmin ping -h 127.0.0.1 -uroot -pdemo_root_dev --silent >/dev/null 2>&1; do
    sleep 1
done

northwind_tables="$(docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'northwind'")"
if [ "${northwind_tables}" = "0" ]; then
    docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev < "${demo_tmp_dir}/northwind-schema.sql"
    docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev < "${demo_tmp_dir}/northwind-data.sql"
fi

sakila_tables="$(docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'sakila'")"
if [ "${sakila_tables}" = "0" ]; then
    docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev < "${demo_tmp_dir}/sakila-db/sakila-schema.sql"
    docker compose exec -T client-mysql-demo mysql -uroot -pdemo_root_dev < "${demo_tmp_dir}/sakila-db/sakila-data.sql"
fi

echo "Northwind and Sakila demo databases are ready."
