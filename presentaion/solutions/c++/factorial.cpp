#include <iostream>

int main() {
    int n;
    std::cin >> n;

    unsigned long long result = 1;
    for (int value = 2; value <= n; value++) {
        result *= static_cast<unsigned long long>(value);
    }

    std::cout << result << '\n';
    return 0;
}
